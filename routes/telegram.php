<?php

/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Telegram\Middleware\OnlyDavidMiddleware;
use SergiX44\Nutgram\Nutgram;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| Here is where you can register telegram handlers for Nutgram. These
| handlers are loaded by the NutgramServiceProvider. Enjoy!
|
*/

$bot->onCommand('example', function (Nutgram $bot) {
    $bot->sendMessage('Hello, world!');
})->description('An example command')->middleware(OnlyDavidMiddleware::class);

$bot->registerCommand(App\Telegram\Commands\WhoamiCommand::class);
$bot->registerCommand(App\Telegram\Commands\TrackCommand::class)
    ->middleware(OnlyDavidMiddleware::class);
$bot->registerCommand(
    new \SergiX44\Nutgram\Handlers\Type\Command(
        function (Nutgram $bot, string $text) {
            App\Telegram\Conversations\TrackConversation::begin($bot, data: [trim($text)]);
        },
        'track {text}'
    )
)->middleware(App\Telegram\Middleware\OnlyDavidMiddleware::class);
$bot->onCommand('track', function (Nutgram $bot) {
    $bot->sendMessage(App\Telegram\Commands\TrackCommand::usageMessage());
})->middleware(OnlyDavidMiddleware::class);

$bot->onMessage(function (Nutgram $bot) {
    $bot->sendMessage('I cannot respond to general conversation yet');
})->middleware(OnlyDavidMiddleware::class);
