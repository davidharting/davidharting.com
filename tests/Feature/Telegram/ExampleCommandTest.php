<?php

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;
use Tests\TestCase;

test('example command replies with environment info', function () {
    /** @var TestCase $this */
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: config('nutgram.owner_user_id'),
        is_bot: false,
        first_name: 'David',
    ));

    $url = config('app.url');
    $env = config('app.env');

    $bot->hearText('/example')
        ->reply()
        ->assertReplyText("Hello! APP_URL={$url} APP_ENV={$env} IS_PULL_REQUEST=no");
});

test('unauthorized user is rejected from example command', function () {
    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: 99999,
        is_bot: false,
        first_name: 'Stranger',
    ));

    $bot->hearText('/example')
        ->reply()
        ->assertReplyText('Sorry, you are not authorized to use this bot.');
});
