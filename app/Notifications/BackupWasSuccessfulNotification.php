<?php

namespace App\Notifications;

use NotificationChannels\Telegram\TelegramMessage;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification as BaseBackupWasSuccessfulNotification;

class BackupWasSuccessfulNotification extends BaseBackupWasSuccessfulNotification
{
    public function toTelegram(): TelegramMessage
    {
        $message = "✅ *Backup Successful*\n\n";
        $message .= "*{$this->applicationName()}*\n\n";

        foreach ($this->backupDestinationProperties() as $name => $value) {
            $message .= "*{$name}:* {$value}\n";
        }

        return TelegramMessage::create()
            ->content($message)
            ->options(['parse_mode' => 'Markdown']);
    }
}
