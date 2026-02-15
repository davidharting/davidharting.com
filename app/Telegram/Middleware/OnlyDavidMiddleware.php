<?php

namespace App\Telegram\Middleware;

use SergiX44\Nutgram\Nutgram;

class OnlyDavidMiddleware
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $davidUserId = (int) config('nutgram.owner_user_id');

        if ($bot->user()?->id !== $davidUserId) {
            $bot->sendMessage('Sorry, you are not authorized to use this bot.');

            return;
        }

        $next($bot);
    }
}
