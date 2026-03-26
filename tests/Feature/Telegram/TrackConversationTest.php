<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\RequestConfirmation;
use Illuminate\Foundation\Testing\TestCase;
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
    app()->bind(RequestConfirmation::class, fn () => tap(new RequestConfirmation(), fn ($t) => $t->handle(new \Laravel\Ai\Tools\Request([]))));

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
