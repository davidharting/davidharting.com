<?php

namespace App\Support;

use Spatie\Backup\Notifications\Notifiable;

class TelegramBackupNotifiable extends Notifiable
{
    public function routeNotificationForTelegram(): ?string
    {
        return config('services.telegram.chat_id');
    }

    public function routeNotificationForLog(): string
    {
        return 'backup-notifications';
    }
}
