<?php

namespace App\Telegram\Commands;

use App\Support\DeploymentInfo;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

class WhoAreYouCommand extends Command
{
    protected string $command = 'whoareyou';

    protected ?string $description = 'Identify which environment this bot is running in';

    public function handle(Nutgram $bot): void
    {
        $lines = collect(DeploymentInfo::values())
            ->map(fn (string $value, string $key): string => "{$key}: {$value}")
            ->values()
            ->all();

        $bot->sendMessage(implode("\n", $lines));
    }
}
