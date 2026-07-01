<?php

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;
use Tests\TestCase;

describe('/whoareyou command', function () {
    afterEach(function () {
        putenv('IS_PULL_REQUEST');
        putenv('RENDER_GIT_COMMIT');
        putenv('RENDER_GIT_BRANCH');
        putenv('RENDER_SERVICE_NAME');
    });

    test('replies with environment info', function () {
        /** @var TestCase $this */
        putenv('IS_PULL_REQUEST=true');
        putenv('RENDER_GIT_COMMIT=abcdef1234567890abcd');
        putenv('RENDER_GIT_BRANCH=my-feature-branch');
        putenv('RENDER_SERVICE_NAME=davidhartingdotcom-web-pr-999');

        /** @var FakeNutgram $bot */
        $bot = app(Nutgram::class);
        $bot->setCommonUser(User::make(
            id: config('nutgram.owner_user_id'),
            is_bot: false,
            first_name: 'David',
        ));

        $expected = implode("\n", [
            'APP_URL: '.config('app.url'),
            'APP_ENV: '.config('app.env'),
            'IS_PULL_REQUEST: yes',
            'GIT_COMMIT: abcdef1234',
            'GIT_BRANCH: my-feature-branch',
            'SERVICE_NAME: davidhartingdotcom-web-pr-999',
        ]);

        $bot->hearText('/whoareyou')
            ->reply()
            ->assertReplyText($expected);
    });

    test('rejects unauthorized user', function () {
        /** @var TestCase $this */
        /** @var FakeNutgram $bot */
        $bot = app(Nutgram::class);
        $bot->setCommonUser(User::make(
            id: 99999,
            is_bot: false,
            first_name: 'Stranger',
        ));

        $bot->hearText('/whoareyou')
            ->reply()
            ->assertReplyText('Sorry, you are not authorized to use this bot.');
    });
});
