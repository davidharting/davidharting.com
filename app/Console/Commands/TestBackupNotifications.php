<?php

namespace App\Console\Commands;

use App\Notifications\BackupHasFailedNotification;
use App\Notifications\BackupWasSuccessfulNotification;
use App\Notifications\CleanupHasFailedNotification;
use App\Notifications\CleanupWasSuccessfulNotification;
use App\Notifications\HealthyBackupWasFoundNotification;
use App\Notifications\UnhealthyBackupWasFoundNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\HealthCheckFailure;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;

class TestBackupNotifications extends Command
{
    protected $signature = 'backup:test-notifications {type? : The notification type to test (success, fail, unhealthy, healthy, cleanup-success, cleanup-fail, or all)}';

    protected $description = 'Send test backup notifications to Telegram';

    public function handle(): int
    {
        $type = $this->argument('type') ?? 'all';

        if (! config('services.telegram.chat_id')) {
            $this->error('TELEGRAM_CHAT_ID is not configured in services.php');

            return self::FAILURE;
        }

        if (! config('services.telegram.token')) {
            $this->error('TELEGRAM_BOT_API_KEY is not configured in services.php');

            return self::FAILURE;
        }

        $this->info('Sending test notifications to Telegram...');
        $this->newLine();

        match ($type) {
            'success' => $this->sendSuccessNotification(),
            'fail' => $this->sendFailureNotification(),
            'unhealthy' => $this->sendUnhealthyNotification(),
            'healthy' => $this->sendHealthyNotification(),
            'cleanup-success' => $this->sendCleanupSuccessNotification(),
            'cleanup-fail' => $this->sendCleanupFailureNotification(),
            'all' => $this->sendAllNotifications(),
            default => $this->error("Unknown notification type: {$type}"),
        };

        $this->newLine();
        $this->info('Done! Check your Telegram chat.');

        return self::SUCCESS;
    }

    protected function sendAllNotifications(): void
    {
        $this->sendSuccessNotification();
        $this->sendFailureNotification();
        $this->sendUnhealthyNotification();
        $this->sendHealthyNotification();
        $this->sendCleanupSuccessNotification();
        $this->sendCleanupFailureNotification();
    }

    protected function sendSuccessNotification(): void
    {
        $this->line('📤 Sending BackupWasSuccessful notification...');

        $event = new BackupWasSuccessful($this->mockBackupDestination());
        $notification = new BackupWasSuccessfulNotification($event);

        Notification::route('telegram', config('services.telegram.chat_id'))
            ->notify($notification);
    }

    protected function sendFailureNotification(): void
    {
        $this->line('📤 Sending BackupHasFailed notification...');

        $event = new BackupHasFailed(
            new \Exception('Test backup failure: Database connection timeout')
        );
        $notification = new BackupHasFailedNotification($event);

        Notification::route('telegram', config('services.telegram.chat_id'))
            ->notify($notification);
    }

    protected function sendUnhealthyNotification(): void
    {
        $this->line('📤 Sending UnhealthyBackupWasFound notification...');

        $backupDestination = $this->mockBackupDestination();
        $status = new BackupDestinationStatus($backupDestination, 1);

        $healthCheck = new MaximumAgeInDays(1);
        $exception = new \Exception('The latest backup is older than 1 day');
        $failure = new HealthCheckFailure($healthCheck, $exception);
        $status->setHealthCheckFailure($failure);

        $event = new UnhealthyBackupWasFound($status);
        $notification = new UnhealthyBackupWasFoundNotification($event);

        Notification::route('telegram', config('services.telegram.chat_id'))
            ->notify($notification);
    }

    protected function sendHealthyNotification(): void
    {
        $this->line('📤 Sending HealthyBackupWasFound notification...');

        $backupDestination = $this->mockBackupDestination();
        $status = new BackupDestinationStatus($backupDestination, 1);

        $event = new HealthyBackupWasFound($status);
        $notification = new HealthyBackupWasFoundNotification($event);

        Notification::route('telegram', config('services.telegram.chat_id'))
            ->notify($notification);
    }

    protected function sendCleanupSuccessNotification(): void
    {
        $this->line('📤 Sending CleanupWasSuccessful notification...');

        $event = new CleanupWasSuccessful($this->mockBackupDestination());
        $notification = new CleanupWasSuccessfulNotification($event);

        Notification::route('telegram', config('services.telegram.chat_id'))
            ->notify($notification);
    }

    protected function sendCleanupFailureNotification(): void
    {
        $this->line('📤 Sending CleanupHasFailed notification...');

        $event = new CleanupHasFailed(
            new \Exception('Test cleanup failure: Unable to delete old backups')
        );
        $notification = new CleanupHasFailedNotification($event);

        Notification::route('telegram', config('services.telegram.chat_id'))
            ->notify($notification);
    }

    protected function mockBackupDestination(): BackupDestination
    {
        $disk = Storage::disk(config('backup.backup.destination.disks')[0]);

        return new BackupDestination(
            disk: $disk,
            backupName: config('backup.backup.name'),
            diskName: $disk->getName()
        );
    }
}
