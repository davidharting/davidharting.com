<?php

namespace App\Console\Commands\Memories;

use App\Memories\MemoryReminders;
use App\Memories\RevealEmails;
use Illuminate\Console\Command;

/**
 * Queues every monthly memories email that is due and hasn't gone out yet.
 *
 * Safe to rerun at any time, and to run twice at once: each email is claimed
 * only once, so no run ever sends one twice.
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
    public function handle(MemoryReminders $reminders, RevealEmails $revealEmails): int
    {
        $claimed = $reminders->claimDue()->concat($revealEmails->claimAllDue());

        $this->components->info("Queued {$claimed->count()} ".str('email')->plural($claimed->count()).'.');

        return self::SUCCESS;
    }
}
