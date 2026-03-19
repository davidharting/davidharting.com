<?php

use App\Ai\Agents\MediaTrackingAgent;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;

test('track command sends user text to MediaTrackingAgent and replies with response', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Got it! I\'ll add The Hobbit to your backlog.']);

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: config('nutgram.owner_user_id'),
        is_bot: false,
        first_name: 'David',
    ));

    $bot->hearText('/track Add The Hobbit to my backlog')
        ->reply()
        ->assertReplyText('Got it! I\'ll add The Hobbit to your backlog.');

    MediaTrackingAgent::assertPrompted('Add The Hobbit to my backlog');
});

test('track command sends reply with HTML parse mode', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['<b>The Hobbit</b> added.']);

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: config('nutgram.owner_user_id'),
        is_bot: false,
        first_name: 'David',
    ));

    $bot->hearText('/track Add The Hobbit to my backlog')
        ->reply()
        ->assertReplyMessage(['parse_mode' => ParseMode::HTML->value]);
});

test('track command without text replies with usage hint', function () {
    /** @var TestCase $this */

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: config('nutgram.owner_user_id'),
        is_bot: false,
        first_name: 'David',
    ));

    $bot->hearText('/track')
        ->reply()
        ->assertReplyText('Usage: /track <description>'."\n".'Example: /track Add The Hobbit to my backlog');
});

test('track command replies with error message when AI provider fails', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(fn () => throw InsufficientCreditsException::forProvider('anthropic'));

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: config('nutgram.owner_user_id'),
        is_bot: false,
        first_name: 'David',
    ));

    $bot->hearText('/track Add The Hobbit to my backlog')
        ->reply()
        ->assertReplyText('Error: AI provider [anthropic] has insufficient credits or quota.');
});

test('unauthorized user is rejected from track command', function () {
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
