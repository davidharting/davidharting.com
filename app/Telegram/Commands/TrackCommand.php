<?php

namespace App\Telegram\Commands;

use App\Ai\Agents\MediaTrackingAgent;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

class TrackCommand extends Command
{
    protected string $command = 'track {text}';

    protected ?string $description = 'Track media with AI assistance';

    public function handle(Nutgram $bot): void
    {
        $text = $bot->getMessage()?->getText() ?? '';

        // Strip the /track prefix to get the user's message
        $text = trim(preg_replace('/^\/track\s*/i', '', $text));

        if ($text === '') {
            $bot->sendMessage('Please provide something to track. Example: /track Add The Hobbit to my backlog');

            return;
        }

        $response = MediaTrackingAgent::make()->prompt($text);

        $bot->sendMessage($response->text);
    }
}
