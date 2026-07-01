<?php

namespace App\Telegram\Commands;

use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

class WhoAreYouCommand extends Command
{
    protected string $command = 'whoareyou';

    protected ?string $description = 'Identify which environment this bot is running in';

    public function handle(Nutgram $bot): void
    {
        $lines = [
            'APP_URL: '.(env('RENDER_EXTERNAL_URL') ?? config('app.url')),
            'APP_ENV: '.config('app.env'),
            'IS_PULL_REQUEST: '.(env('IS_PULL_REQUEST') ? 'yes' : 'no'),
            'GIT_COMMIT: '.(env('RENDER_GIT_COMMIT') ? substr(env('RENDER_GIT_COMMIT'), 0, 10) : 'unknown'),
            'GIT_BRANCH: '.(env('RENDER_GIT_BRANCH') ?? 'unknown'),
            'SERVICE_NAME: '.(env('RENDER_SERVICE_NAME') ?? 'unknown'),
        ];

        $bot->sendMessage(implode("\n", $lines));
    }
}
