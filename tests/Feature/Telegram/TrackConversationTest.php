<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\RequestConfirmation;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Sleep;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
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
 * This allows TrackConversation (which resolves the tool via app()) to receive
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

test('/track sends agent response as plain text when confirmation not requested', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Which Dune did you mean?']);

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track mark dune as finished')
        ->reply()
        ->assertReplyText('Which Dune did you mean?')
        ->assertActiveConversation();
});

test('/track plain text response includes an End button', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Which Dune did you mean?']);

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track mark dune as finished')
        ->reply()
        ->assertReplyMessage([
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => 'End', 'callback_data' => 'end'],
                ]],
            ],
        ]);
});

test('tapping End sends acknowledgement and ends the conversation', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Which Dune did you mean?']);

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track mark dune as finished')->reply();

    $bot->hearCallbackQueryData('end')
        ->reply()
        ->assertReplyText('Conversation ended.', index: 1)
        ->assertNoConversation();
});

test('/track keeps conversation active for follow-up after plain text response', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Which Dune did you mean?', 'Got it.']);

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track mark dune as finished')->reply();
    $bot->hearText('the novel')
        ->reply()
        ->assertReplyText('Got it.')
        ->assertActiveConversation();
});

test('/track sends message with inline keyboard when agent calls RequestConfirmation', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.']);
    PreTriggeredConfirmation::bind();

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track Add The Hobbit to my backlog')
        ->reply()
        ->assertReplyText('Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.')
        ->assertReplyMessage([
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => '✓ Confirm', 'callback_data' => 'confirm'],
                    ['text' => 'End', 'callback_data' => 'end'],
                ]],
            ],
        ])
        ->assertActiveConversation();
});

test('tapping Confirm calls MediaTrackingAgent with the confirmed plan and sends summary', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake([
        'I\'ll add <b>The Hobbit</b> (1937) by J.R.R. Tolkien — Book to your library. Sound good?',
        '✓ Added The Hobbit (1937) by J.R.R. Tolkien — Book.',
    ]);
    PreTriggeredConfirmation::bind();

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track Add The Hobbit')->reply();

    $bot->hearCallbackQueryData('confirm')
        ->reply()
        ->assertReplyText('On it! I\'ll report back when it\'s done.', index: 2)
        ->assertReplyText('✓ Added The Hobbit (1937) by J.R.R. Tolkien — Book.', index: 3)
        ->assertNoConversation();
});

test('tapping End while awaiting confirmation ends the conversation and no DB rows are created', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.']);
    PreTriggeredConfirmation::bind();

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track Add The Hobbit')->reply();

    $bot->hearCallbackQueryData('end')
        ->reply()
        ->assertReplyText('Conversation ended.', index: 2)
        ->assertNoConversation();

    $this->assertDatabaseCount('media', 0);
    $this->assertDatabaseCount('media_events', 0);
});

test('typing text while awaiting confirmation runs a new agent turn', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake([
        'I\'ll add The Hobbit. Sound good?',
        'Got it — I\'ll add it as started instead.',
    ]);
    PreTriggeredConfirmation::bind();

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track Add The Hobbit')->reply();

    // Unbind pre-triggered confirmation so the follow-up turn is a plain response
    app()->bind(RequestConfirmation::class, fn () => new RequestConfirmation);

    $bot->hearText('actually make it started')
        ->reply()
        ->assertReplyText('Got it — I\'ll add it as started instead.')
        ->assertActiveConversation();
});

test('/track ends conversation with error message when AI provider fails', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(fn () => throw InsufficientCreditsException::forProvider('anthropic'));

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track Add The Hobbit')
        ->reply()
        ->assertReplyText('Error: AI provider [anthropic] has insufficient credits or quota.')
        ->assertNoConversation();
});

test('/track persists the conversation to the database', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Which Dune did you mean?']);

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track mark dune as finished')->reply();

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

test('/track falls back to a default confirmation message when agent text is empty', function () {
    /** @var TestCase $this */
    // The agent called RequestConfirmation but emitted no response text. We must
    // not send Telegram an empty message (it rejects it with a 400).
    MediaTrackingAgent::fake(['']);
    PreTriggeredConfirmation::bind();

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track Add The Hobbit')
        ->reply()
        ->assertReplyText('Ready to log this. Confirm?')
        ->assertReplyMessage([
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => '✓ Confirm', 'callback_data' => 'confirm'],
                    ['text' => 'End', 'callback_data' => 'end'],
                ]],
            ],
        ])
        ->assertActiveConversation();
});

test('/track falls back to a default plain message when agent text is empty', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['']);

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track mark dune as finished')
        ->reply()
        ->assertReplyText("Sorry, I didn't catch that. Could you say it another way?")
        ->assertActiveConversation();
});

test('/track recovers from an unexpected error and replies instead of throwing', function () {
    /** @var TestCase $this */
    // A non-AiException (e.g. a bug or a Telegram API rejection) must not bubble
    // up and 500 the webhook — that is what causes Telegram's retry storm.
    MediaTrackingAgent::fake(fn () => throw new RuntimeException('boom'));

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track Add The Hobbit')
        ->reply()
        ->assertReplyText('Sorry, something went wrong on my end. Please try again.')
        ->assertNoConversation();
});

test('/track retries a transient provider failure and then succeeds', function () {
    /** @var TestCase $this */
    Sleep::fake();

    $calls = 0;
    MediaTrackingAgent::fake(function () use (&$calls) {
        $calls++;

        if ($calls === 1) {
            throw new ProviderOverloadedException('overloaded');
        }

        return 'Which Dune did you mean?';
    });

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track mark dune as finished')
        ->reply()
        ->assertReplyText('Which Dune did you mean?')
        ->assertActiveConversation();

    expect($calls)->toBe(2);
});

test('/track gives up after exhausting transient retries and reports the error', function () {
    /** @var TestCase $this */
    Sleep::fake();

    $calls = 0;
    MediaTrackingAgent::fake(function () use (&$calls) {
        $calls++;
        throw new ProviderOverloadedException('overloaded');
    });

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track mark dune as finished')
        ->reply()
        ->assertReplyText('Error: overloaded')
        ->assertNoConversation();

    // Three attempts total: the initial try plus two retries.
    expect($calls)->toBe(3);
});

test('unauthorized user is rejected from /track', function () {
    /** @var TestCase $this */

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: 99999,
        is_bot: false,
        first_name: 'Stranger',
    ));

    $bot->hearText('/track Add something')
        ->reply()
        ->assertReplyText('Sorry, you are not authorized to use this bot.');
});
