<?php

namespace App\Notifications;

use NotificationChannels\Telegram\TelegramMessage;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification as BaseBackupHasFailedNotification;

class BackupHasFailedNotification extends BaseBackupHasFailedNotification
{
    public function toTelegram(): TelegramMessage
    {
        $message = "❌ *Backup Failed*\n\n";
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
