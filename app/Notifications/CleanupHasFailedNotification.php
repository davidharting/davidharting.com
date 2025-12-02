<?php

namespace App\Notifications;

use NotificationChannels\Telegram\TelegramMessage;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification as BaseCleanupHasFailedNotification;

class CleanupHasFailedNotification extends BaseCleanupHasFailedNotification
{
    public function toTelegram(): TelegramMessage
    {
        $message = "❌ *Cleanup Failed*\n\n";
        $message .= "*{$this->applicationName()}*\n\n";
        $message .= "*Error:* {$this->event->exception->getMessage()}\n\n";

        foreach ($this->backupDestinationProperties() as $name => $value) {
            $message .= "*{$name}:* {$value}\n";
        }

        return TelegramMessage::create()
            ->content($message)
            ->options(['parse_mode' => 'Markdown']);
    }
}
