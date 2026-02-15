<?php

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;

test('whoami command replies with user id', function () {
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: config('nutgram.david'),
        is_bot: false,
        first_name: 'David',
    ));

    $bot->hearText('/whoami')
        ->reply()
        ->assertReplyText((string) config('nutgram.david'));
});

test('unauthorized user is rejected from whoami command', function () {
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: 99999,
        is_bot: false,
        first_name: 'Stranger',
    ));

    $bot->hearText('/whoami')
        ->reply()
        ->assertReplyText('Sorry, you are not authorized to use this bot.');
});
