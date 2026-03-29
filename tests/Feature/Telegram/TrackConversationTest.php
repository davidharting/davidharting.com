<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\RequestConfirmation;
use Illuminate\Foundation\Testing\TestCase;
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
    MediaTrackingAgent::fake(fn () => throw \Laravel\Ai\Exceptions\InsufficientCreditsException::forProvider('anthropic'));

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
