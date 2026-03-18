<?php

namespace App\Telegram\Commands;

use App\Ai\Agents\MediaTrackingAgent;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

class TrackCommand extends Command
{
    protected string $command = 'track {text}';

    protected ?string $description = 'Track media with AI assistance';

    public function handle(Nutgram $bot, string $text): void
    {
        // Should we make() in a way that the container can inject dependencies if we have them in the future?
        $response = MediaTrackingAgent::make()->prompt(trim($text));

        $bot->sendMessage($response->text);
    }
}
