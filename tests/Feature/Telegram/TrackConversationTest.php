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
                    ['text' => '✗ Cancel', 'callback_data' => 'cancel'],
                ]],
            ],
        ])
        ->assertActiveConversation();
});

test('tapping Confirm ends the conversation with acknowledgement', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.']);
    PreTriggeredConfirmation::bind();

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track Add The Hobbit')->reply();

    $bot->hearCallbackQueryData('confirm')
        ->reply()
        ->assertReplyText('✓ Done. (DB writes coming in next milestone)', index: 1)
        ->assertNoConversation();
});

test('tapping Cancel ends the conversation with cancellation message', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.']);
    PreTriggeredConfirmation::bind();

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track Add The Hobbit')->reply();

    $bot->hearCallbackQueryData('cancel')
        ->reply()
        ->assertReplyText('Cancelled. Nothing was changed.', index: 1)
        ->assertNoConversation();
});

test('stray text while awaiting confirmation sends a reminder and conversation stays active', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.']);
    PreTriggeredConfirmation::bind();

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());
    $bot->willStartConversation();

    $bot->hearText('/track Add The Hobbit')->reply();

    $bot->hearText('actually wait')
        ->reply()
        ->assertReplyText('Please tap Confirm or Cancel.')
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
