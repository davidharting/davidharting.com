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
