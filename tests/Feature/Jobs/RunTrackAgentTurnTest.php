<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\RequestConfirmation;
use App\Jobs\RunTrackAgentTurn;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Tools\Request;
use Psr\Http\Message\RequestInterface as PsrRequest;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;

/**
 * Parks a real TrackConversation on its `working` step and returns the turn that was
 * queued for it, so job behaviour is exercised against conversation state the webhook
 * actually produced rather than a hand-built fixture.
 */
function queuedTurn(string $text = '/track mark dune as finished'): RunTrackAgentTurn
{
    Queue::fake();

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: config('nutgram.owner_user_id'),
        is_bot: false,
        first_name: 'David',
    ));
    $bot->willStartConversation();
    $bot->hearText($text)->reply();

    return Queue::pushed(RunTrackAgentTurn::class)->first();
}

/**
 * A Nutgram instance with no inbound Update, standing in for the queue worker — the
 * job has to address every Telegram call and conversation write explicitly there.
 */
function workerBot(): FakeNutgram
{
    app()->forgetInstance(Nutgram::class);

    return app(Nutgram::class);
}

describe('handle()', function () {
    test('sends the agent reply with an End button and parks for a follow-up', function () {
        /** @var TestCase $this */
        $turn = queuedTurn();
        MediaTrackingAgent::fake(['Which Dune did you mean?']);

        $worker = workerBot();
        $turn->handle($worker);

        $worker
            ->assertReplyText('Which Dune did you mean?', index: 0)
            ->assertReplyMessage([
                'chat_id' => $turn->chatId,
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => 'End', 'callback_data' => 'end'],
                    ]],
                ],
            ], index: 0)
            ->assertActiveConversation($turn->userId, $turn->chatId);
    });

    test('offers Confirm and Cancel when the agent requests confirmation', function () {
        /** @var TestCase $this */
        $turn = queuedTurn('/track Add The Hobbit');
        MediaTrackingAgent::fake(['I\'ll add <b>The Hobbit</b>. Sound good?']);

        app()->bind(RequestConfirmation::class, function () {
            $tool = new RequestConfirmation;
            $tool->handle(new Request([]));

            return $tool;
        });

        $worker = workerBot();
        $turn->handle($worker);

        $worker->assertReplyMessage([
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => '✓ Confirm', 'callback_data' => 'confirm'],
                    ['text' => 'End', 'callback_data' => 'end'],
                ]],
            ],
        ], index: 0);
    });

    test('a write turn reports the result and closes the conversation', function () {
        /** @var TestCase $this */
        $turn = queuedTurn();
        MediaTrackingAgent::fake(['✓ Added The Hobbit (1937) by J.R.R. Tolkien — Book.']);

        $writeTurn = new RunTrackAgentTurn(
            chatId: $turn->chatId,
            userId: $turn->userId,
            threadId: $turn->threadId,
            aiConversationId: $turn->aiConversationId,
            turnId: $turn->turnId,
            userText: 'The user confirmed. Execute the plan.',
            canWrite: true,
        );

        $worker = workerBot();
        $writeTurn->handle($worker);

        $worker
            ->assertReplyText('✓ Added The Hobbit (1937) by J.R.R. Tolkien — Book.', index: 0)
            ->assertNoConversation($turn->userId, $turn->chatId);
    });

    test('discards its reply when the conversation was ended while it was running', function () {
        /** @var TestCase $this */
        $turn = queuedTurn();
        MediaTrackingAgent::fake(['Which Dune did you mean?']);

        // Stands in for /end arriving mid-turn.
        $worker = workerBot();
        $worker->endConversation($turn->userId, $turn->chatId, $turn->threadId);

        $turn->handle($worker);

        $worker->assertNoReply();
    });

    test('discards its reply when a newer turn superseded it', function () {
        /** @var TestCase $this */
        $turn = queuedTurn();
        MediaTrackingAgent::fake(['Which Dune did you mean?']);

        $stale = new RunTrackAgentTurn(
            chatId: $turn->chatId,
            userId: $turn->userId,
            threadId: $turn->threadId,
            aiConversationId: $turn->aiConversationId,
            turnId: (string) Str::uuid(),
            userText: 'an abandoned question',
        );

        $worker = workerBot();
        $stale->handle($worker);

        $worker->assertNoReply();

        // The conversation is untouched, still parked awaiting the turn it does own.
        $worker->assertActiveConversation($turn->userId, $turn->chatId);
    });
});

describe('failed()', function () {
    test('reports the error and ends the conversation', function () {
        /** @var TestCase $this */
        $turn = queuedTurn();

        $worker = workerBot();
        $turn->failed(InsufficientCreditsException::forProvider('anthropic'));

        $worker
            ->assertReplyText('Error: AI provider [anthropic] has insufficient credits or quota.', index: 0)
            ->assertNoConversation($turn->userId, $turn->chatId);
    });

    test('reports the error as plain text, so a "<" in it cannot break the send', function () {
        /** @var TestCase $this */
        $turn = queuedTurn();

        $worker = workerBot();
        $turn->failed(new RuntimeException('call to <MediaWebSearchAgent> failed'));

        $worker
            ->assertReplyText('Error: call to <MediaWebSearchAgent> failed', index: 0)
            ->assertRaw(
                fn (PsrRequest $request) => ! array_key_exists('parse_mode', FakeNutgram::getActualData($request)),
                index: 0,
                message: 'The error message was sent with a parse mode',
            );
    });

    test('stays quiet when the conversation is already gone', function () {
        /** @var TestCase $this */
        $turn = queuedTurn();

        $worker = workerBot();
        $worker->endConversation($turn->userId, $turn->chatId, $turn->threadId);

        $turn->failed(InsufficientCreditsException::forProvider('anthropic'));

        $worker->assertNoReply();
    });
});

describe('queue configuration', function () {
    test('a turn is never retried, so a confirmed plan cannot be applied twice', function () {
        /** @var TestCase $this */
        expect((new RunTrackAgentTurn(1, 1, null, 'c', 't', 'hi'))->tries)->toBe(1);
    });

    test('retry_after outruns the job timeout so a running turn is not re-reserved', function () {
        /** @var TestCase $this */
        $job = new RunTrackAgentTurn(1, 1, null, 'c', 't', 'hi');

        expect(config('queue.connections.database.retry_after'))->toBeGreaterThan($job->timeout);
    });
});
