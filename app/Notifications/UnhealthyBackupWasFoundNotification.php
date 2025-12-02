<?php

namespace App\Notifications;

use NotificationChannels\Telegram\TelegramMessage;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification as BaseUnhealthyBackupWasFoundNotification;

class UnhealthyBackupWasFoundNotification extends BaseUnhealthyBackupWasFoundNotification
{
    public function toTelegram(): TelegramMessage
    {
        $message = "⚠️ *Unhealthy Backup Found*\n\n";
        $message .= "*{$this->applicationName()}*\n\n";
        $message .= "*Problem:* {$this->problemDescription()}\n\n";

        foreach ($this->backupDestinationProperties() as $name => $value) {
            $message .= "*{$name}:* {$value}\n";
        }

        if ($this->failure()->wasUnexpected()) {
            $message .= "\n*Health Check:* {$this->failure()->healthCheck()->name()}\n";
            $message .= "*Exception:* {$this->failure()->exception()->getMessage()}\n";
        }

        return TelegramMessage::create()
            ->content($message)
            ->options(['parse_mode' => 'Markdown']);
    }
}
