<?php

namespace App\Telegram\Commands;

use App\Ai\Agents\MediaTrackingAgent;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\AiException;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

class TrackCommand extends Command
{
    protected string $command = 'track {text}';

    protected ?string $description = 'Track media with AI assistance';

    public static function usageMessage(): string
    {
        return "Usage: /track <description>\nExample: /track Add The Hobbit to my backlog";
    }

    public function handle(Nutgram $bot, string $text): void
    {
        try {
            $response = MediaTrackingAgent::make()->prompt(trim($text));
            $bot->sendMessage($response->text, parse_mode: ParseMode::HTML);
        } catch (AiException $e) {
            Log::error('MediaTrackingAgent failed', ['exception' => $e]);
            // Okay to surface exception details — only David can use this command (OnlyDavidMiddleware)
            $bot->sendMessage("Error: {$e->getMessage()}");
        }
    }
}
