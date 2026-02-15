<?php

namespace App\Telegram\Commands;

use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

class WhoamiCommand extends Command
{
    protected string $command = 'whoami';

    protected ?string $description = 'Get your Telegram User ID';

    public function handle(Nutgram $bot): void
    {
        $bot->sendMessage((string) $bot->user()?->id);
    }
}
