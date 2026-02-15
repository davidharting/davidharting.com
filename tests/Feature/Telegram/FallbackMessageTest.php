<?php

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;

test('general messages get a fallback reply', function () {
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: config('nutgram.owner_user_id'),
        is_bot: false,
        first_name: 'David',
    ));

    $bot->hearText('What is the meaning of life?')
        ->reply()
        ->assertReplyText('I cannot respond to general conversation yet');
});

test('unauthorized user is rejected from general messages', function () {
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: 99999,
        is_bot: false,
        first_name: 'Stranger',
    ));

    $bot->hearText('Hello there')
        ->reply()
        ->assertReplyText('Sorry, you are not authorized to use this bot.');
});
