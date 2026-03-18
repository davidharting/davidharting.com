<?php

namespace App\Telegram\Commands;

use App\Ai\Agents\MediaTrackingAgent;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\AiException;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

class TrackCommand extends Command
{
    protected string $command = 'track {text}';

    protected ?string $description = 'Track media with AI assistance';

    public function handle(Nutgram $bot, string $text): void
    {
        try {
            $response = MediaTrackingAgent::make()->prompt(trim($text));
            $bot->sendMessage($response->text);
        } catch (AiException $e) {
            Log::error('MediaTrackingAgent failed', ['exception' => $e]);
            $bot->sendMessage('Sorry, I ran into an issue processing your request. Please try again later.');
        }
    }
}
