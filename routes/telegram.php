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

$bot->onCommand('whoami', App\Telegram\Commands\WhoamiCommand::class);

$bot->onMessage(function (Nutgram $bot) {
    $bot->sendMessage('I cannot respond to general conversation yet');
})->middleware(OnlyDavidMiddleware::class);
