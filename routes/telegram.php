<?php

/** @var Nutgram $bot */

use App\Telegram\Commands\WhoamiCommand;
use App\Telegram\Commands\WhoAreYouCommand;
use App\Telegram\Conversations\TrackConversation;
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

$bot->registerCommand(WhoamiCommand::class);

$bot->registerCommand(WhoAreYouCommand::class)->middleware(OnlyDavidMiddleware::class);

$bot->onCommand('track {text}', TrackConversation::class)->middleware(OnlyDavidMiddleware::class);

$bot->onCommand('track', function (Nutgram $bot) {
    $bot->sendMessage("Usage: /track <description>\nExample: /track Add The Hobbit to my backlog");
})->middleware(OnlyDavidMiddleware::class);

// Nutgram terminates an active conversation whenever a handler with a pattern matches,
// so by the time this closure runs any in-flight TrackConversation has already been
// purged from the conversation cache. That also invalidates its turn id, which is how
// the queued agent turn learns to discard its result instead of replying.
$bot->onCommand('end', function (Nutgram $bot) {
    $bot->sendMessage('Conversation ended.');
})->description('End the current conversation')->middleware(OnlyDavidMiddleware::class);

$bot->onMessage(function (Nutgram $bot) {
    $bot->sendMessage('I cannot respond to general conversation yet');
})->middleware(OnlyDavidMiddleware::class);
