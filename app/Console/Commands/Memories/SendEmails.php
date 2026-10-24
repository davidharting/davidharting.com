<?php

namespace App\Console\Commands\Memories;

use App\Memories\MemoryReminders;
use Illuminate\Console\Command;

/**
 * Queues every monthly memories email that is due and hasn't gone out yet.
 *
 * A Render cron runs it at 13:00 and 14:00 UTC, so one of the two runs falls
 * at or just after 9am Eastern whether or not daylight saving is on. Running
 * it more often, or twice by accident, never sends an email twice.
 */
class SendEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memories:send-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue the monthly memories emails that are due';

    /**
     * Execute the console command.
     */
    public function handle(MemoryReminders $reminders): int
    {
        $claimed = $reminders->claimDue();

        $this->components->info("Queued {$claimed->count()} ".str('email')->plural($claimed->count()).'.');

        return self::SUCCESS;
    }
}
