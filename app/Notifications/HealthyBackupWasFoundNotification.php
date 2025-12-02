<?php

namespace App\Notifications;

use NotificationChannels\Telegram\TelegramMessage;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification as BaseHealthyBackupWasFoundNotification;

class HealthyBackupWasFoundNotification extends BaseHealthyBackupWasFoundNotification
{
    public function toTelegram(): TelegramMessage
    {
        $message = "✅ *Healthy Backup Found*\n\n";
        $message .= "*{$this->applicationName()}*\n\n";

        foreach ($this->backupDestinationProperties() as $name => $value) {
            $message .= "*{$name}:* {$value}\n";
        }

        return TelegramMessage::create()
            ->content($message)
            ->options(['parse_mode' => 'Markdown']);
    }
}
