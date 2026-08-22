<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\RequestConfirmation;
use App\Jobs\RunTrackAgentTurn;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;

function davidUser(): User
{
    return User::make(
        id: config('nutgram.owner_user_id'),
        is_bot: false,
        first_name: 'David',
    );
}

/**
 * Binds a RequestConfirmation instance to the container that has already been
 * triggered, simulating the agent having called the tool during its turn.
 * This allows RunTrackAgentTurn (which resolves the tool via app()) to receive
 * an already-fired instance in tests without going through a real agent call.
 */
class PreTriggeredConfirmation
{
    public static function bind(): void
    {
        app()->bind(RequestConfirmation::class, function () {
            $tool = new RequestConfirmation;
            $tool->handle(new Request([]));

            return $tool;
        });
    }
}

function trackBot(): FakeNutgram
{
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    return $bot;
}

describe('dispatching a turn', function () {
    test('/track hands the agent turn to the queue instead of running it inline', function () {
        /** @var TestCase $this */
        Queue::fake();

        trackBot()
            ->hearText('/track mark dune as finished')
            ->reply()
            ->assertActiveConversation();

        Queue::assertPushed(RunTrackAgentTurn::class, function (RunTrackAgentTurn $job) {
            return $job->userText === 'mark dune as finished'
                && $job->canWrite === false
                && $job->userId === (int) config('nutgram.owner_user_id')
                && $job->turnId !== '';
        });
    });

    test('/track acknowledges immediately with a typing action', function () {
        /** @var TestCase $this */
        Queue::fake();

        trackBot()
            ->hearText('/track mark dune as finished')
            ->reply()
            ->assertReply('sendChatAction', ['action' => 'typing'], index: 0);
    });

    test('/track creates the AI conversation before dispatching', function () {
        /** @var TestCase $this */
        Queue::fake();

        trackBot()->hearText('/track mark dune as finished')->reply();

        $this->assertDatabaseCount('agent_conversations', 1);

        Queue::assertPushed(RunTrackAgentTurn::class, fn (RunTrackAgentTurn $job) => $job->aiConversationId !== '');
    });

    test('a follow-up message dispatches a fresh turn on the same AI conversation', function () {
        /** @var TestCase $this */
        MediaTrackingAgent::fake(['Which Dune did you mean?']);

        $bot = trackBot();
        $bot->hearText('/track mark dune as finished')->reply();

        Queue::fake();

        $bot->hearText('the novel')->reply();

        Queue::assertPushed(RunTrackAgentTurn::class, function (RunTrackAgentTurn $job) {
            return $job->userText === 'the novel'
                && $job->aiConversationId === DB::table('agent_conversations')->value('id');
        });
    });
});

describe('working()', function () {
    test('a second message while a turn is running does not start another turn', function () {
        /** @var TestCase $this */
        Queue::fake();

        $bot = trackBot();
        $bot->hearText('/track mark dune as finished')->reply();

        $bot->hearText('actually never mind')
            ->reply()
            ->assertReplyText('Still working on your last message — one moment.', index: 0)
            ->assertActiveConversation();

        Queue::assertPushed(RunTrackAgentTurn::class, 1);
    });

    test('a stale button tap while a turn is running is answered, not acted on', function () {
        /** @var TestCase $this */
        Queue::fake();

        $bot = trackBot();
        $bot->hearText('/track mark dune as finished')->reply();

        $bot->hearCallbackQueryData('confirm')
            ->reply()
            ->assertReply('answerCallbackQuery', ['text' => 'Still working on your last message.'], index: 0)
            ->assertActiveConversation();

        Queue::assertPushed(RunTrackAgentTurn::class, 1);
    });

    test('tapping End while a turn is running ends the conversation', function () {
        /** @var TestCase $this */
        Queue::fake();

        $bot = trackBot();
        $bot->hearText('/track mark dune as finished')->reply();

        $bot->hearCallbackQueryData('end')
            ->reply()
            ->assertReplyText('Conversation ended.', index: 1)
            ->assertNoConversation();
    });
});

describe('awaitConfirmation()', function () {
    test('tapping Confirm dispatches a write turn', function () {
        /** @var TestCase $this */
        MediaTrackingAgent::fake(['I\'ll add <b>The Hobbit</b>. Sound good?']);
        PreTriggeredConfirmation::bind();

        $bot = trackBot();
        $bot->hearText('/track Add The Hobbit')->reply();

        Queue::fake();

        $bot->hearCallbackQueryData('confirm')
            ->reply()
            ->assertReplyText('On it! I\'ll report back when it\'s done.', index: 2);

        Queue::assertPushed(RunTrackAgentTurn::class, function (RunTrackAgentTurn $job) {
            return $job->canWrite === true
                && $job->userText === 'The user confirmed. Execute the plan.';
        });
    });

    test('tapping End while awaiting confirmation ends it and writes nothing', function () {
        /** @var TestCase $this */
        MediaTrackingAgent::fake(['I\'ll add <b>The Hobbit</b>. Sound good?']);
        PreTriggeredConfirmation::bind();

        $bot = trackBot();
        $bot->hearText('/track Add The Hobbit')->reply();

        $bot->hearCallbackQueryData('end')
            ->reply()
            ->assertReplyText('Conversation ended.', index: 2)
            ->assertNoConversation();

        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('media_events', 0);
    });

    test('typing text while awaiting confirmation dispatches a read-only turn', function () {
        /** @var TestCase $this */
        MediaTrackingAgent::fake(['I\'ll add <b>The Hobbit</b>. Sound good?']);
        PreTriggeredConfirmation::bind();

        $bot = trackBot();
        $bot->hearText('/track Add The Hobbit')->reply();

        Queue::fake();

        $bot->hearText('actually make it started')->reply();

        Queue::assertPushed(RunTrackAgentTurn::class, function (RunTrackAgentTurn $job) {
            return $job->canWrite === false
                && $job->userText === 'actually make it started';
        });
    });
});

describe('/end', function () {
    test('purges an in-flight conversation', function () {
        /** @var TestCase $this */
        Queue::fake();

        $bot = trackBot();
        $bot->hearText('/track mark dune as finished')->reply();

        $bot->hearText('/end')
            ->reply()
            ->assertReplyText('Conversation ended.', index: 0)
            ->assertNoConversation();
    });

    test('is harmless when no conversation is active', function () {
        /** @var TestCase $this */
        trackBot()
            ->hearText('/end')
            ->reply()
            ->assertReplyText('Conversation ended.', index: 0)
            ->assertNoConversation();
    });

    test('a new /track replaces the conversation left by an abandoned turn', function () {
        /** @var TestCase $this */
        Queue::fake();

        $bot = trackBot();
        $bot->hearText('/track mark dune as finished')->reply();
        $bot->hearText('/track add the hobbit')->reply();

        Queue::assertPushed(RunTrackAgentTurn::class, 2);

        // Two distinct AI conversations: the second /track started over rather than
        // continuing the first, which the abandoned turn's turn id no longer matches.
        $this->assertDatabaseCount('agent_conversations', 2);
    });
});

describe('end to end on the queue', function () {
    test('a completed turn leaves the conversation ready for the next message', function () {
        /** @var TestCase $this */
        MediaTrackingAgent::fake(['Which Dune did you mean?', 'Got it.']);

        $bot = trackBot();

        $bot->hearText('/track mark dune as finished')
            ->reply()
            ->assertReplyText('Which Dune did you mean?', index: 1);

        // Reaching the agent again (rather than the working() holding reply) is what
        // proves the queued turn moved the conversation off its `working` step.
        $bot->hearText('the novel')
            ->reply()
            ->assertReplyText('Got it.', index: 1)
            ->assertActiveConversation();
    });

    test('the confirm flow runs both turns on the queue and closes the conversation', function () {
        /** @var TestCase $this */
        MediaTrackingAgent::fake([
            'I\'ll add <b>The Hobbit</b>. Sound good?',
            '✓ Added The Hobbit (1937) by J.R.R. Tolkien — Book.',
        ]);
        PreTriggeredConfirmation::bind();

        $bot = trackBot();
        $bot->hearText('/track Add The Hobbit')->reply();

        $bot->hearCallbackQueryData('confirm')
            ->reply()
            ->assertReplyText('On it! I\'ll report back when it\'s done.', index: 2)
            ->assertReplyText('✓ Added The Hobbit (1937) by J.R.R. Tolkien — Book.', index: 4)
            ->assertNoConversation();
    });

    test('the queued turn writes its messages to the AI conversation', function () {
        /** @var TestCase $this */
        MediaTrackingAgent::fake(['Which Dune did you mean?']);

        trackBot()->hearText('/track mark dune as finished')->reply();

        $this->assertDatabaseCount('agent_conversations', 1);
        $this->assertDatabaseCount('agent_conversation_messages', 2);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'agent' => MediaTrackingAgent::class,
            'role' => 'user',
            'content' => 'mark dune as finished',
        ]);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'agent' => MediaTrackingAgent::class,
            'role' => 'assistant',
            'content' => 'Which Dune did you mean?',
        ]);
    });
});

test('unauthorized user is rejected from /track', function () {
    /** @var TestCase $this */
    Queue::fake();

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(id: 999, is_bot: false, first_name: 'Someone'));
    $bot->willStartConversation();

    $bot->hearText('/track Add The Hobbit')
        ->reply()
        ->assertReplyText('Sorry, you are not authorized to use this bot.');

    Queue::assertNothingPushed();
});
