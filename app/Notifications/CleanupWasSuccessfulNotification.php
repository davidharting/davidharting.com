<?php

namespace App\Notifications;

use NotificationChannels\Telegram\TelegramMessage;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification as BaseCleanupWasSuccessfulNotification;

class CleanupWasSuccessfulNotification extends BaseCleanupWasSuccessfulNotification
{
    public function toTelegram(): TelegramMessage
    {
        $message = "🧹 *Cleanup Successful*\n\n";
        $message .= "*{$this->applicationName()}*\n\n";

        foreach ($this->backupDestinationProperties() as $name => $value) {
            $message .= "*{$name}:* {$value}\n";
        }

        return TelegramMessage::create()
            ->content($message)
            ->options(['parse_mode' => 'Markdown']);
    }
}
