<?php

use App\Notifications\BackupHasFailedNotification;
use App\Notifications\BackupWasSuccessfulNotification;
use App\Support\TelegramBackupNotifiable;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Telegram\TelegramMessage;

test('backup notifications can be sent via Telegram', function () {
    Notification::fake();

    $notifiable = new TelegramBackupNotifiable;

    // Test BackupWasSuccessfulNotification
    $event = new \Spatie\Backup\Events\BackupWasSuccessful(
        backupDestination: mockBackupDestination()
    );
    $notification = new BackupWasSuccessfulNotification($event);
    $telegramMessage = $notification->toTelegram();

    expect($telegramMessage)->toBeInstanceOf(TelegramMessage::class);
    expect($telegramMessage->toArray()['text'])->toContain('Backup Successful');
});

test('backup failed notification includes error message', function () {
    $exception = new \Exception('Database connection failed');
    $event = new \Spatie\Backup\Events\BackupHasFailed(
        exception: $exception
    );

    $notification = new BackupHasFailedNotification($event);
    $telegramMessage = $notification->toTelegram();

    expect($telegramMessage->toArray()['text'])->toContain('Backup Failed');
    expect($telegramMessage->toArray()['text'])->toContain('Database connection failed');
});

test('TelegramBackupNotifiable returns correct chat ID', function () {
    config(['services.telegram.chat_id' => '123456789']);

    $notifiable = new TelegramBackupNotifiable;

    expect($notifiable->routeNotificationForTelegram())->toBe('123456789');
});

function mockBackupDestination(): \Spatie\Backup\BackupDestination\BackupDestination
{
    $disk = Storage::fake('backups');

    return new \Spatie\Backup\BackupDestination\BackupDestination(
        disk: $disk,
        backupName: 'test-backup',
        diskName: 'backups'
    );
}
