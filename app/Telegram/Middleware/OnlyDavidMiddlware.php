<?php

namespace App\Telegram\Middleware;

use SergiX44\Nutgram\Nutgram;

class OnlyDavidMiddlware
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $davidUserId = (int) config('nutgram.david');

        if ($bot->user()?->id !== $davidUserId) {
            $bot->sendMessage('Sorry, you are not authorized to use this bot.');
            return;
        }

        $next($bot);
    }
}
