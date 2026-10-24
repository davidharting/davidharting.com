<?php

namespace App\Console\Commands\Memories;

use App\Models\MemoryParticipant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Makes an existing user one of the two memory participants.
 *
 * This is how participants are provisioned in production: run once per person.
 * It is safe to run again: someone who is already a participant is left as they
 * are. Their display name belongs to their account, set by users:add.
 *
 * It refuses a third participant rather than letting the mistake through, since
 * the feature is live only with exactly two.
 */
class AddParticipant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memories:add-participant
            {email : The user to make a participant}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make an existing user one of the two monthly memories participants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No user with the email [{$email}]. Create their account first with users:add.");

            return self::FAILURE;
        }

        if ($user->memoryParticipant !== null) {
            $this->components->info("{$user->email} is already a memory participant.");
            $this->reportStatus();

            return self::SUCCESS;
        }

        if (MemoryParticipant::count() >= 2) {
            $this->refuseThirdParticipant();

            return self::FAILURE;
        }

        MemoryParticipant::create(['user_id' => $user->id]);

        $this->components->info("{$user->email} is now a memory participant.");
        $this->reportStatus();

        return self::SUCCESS;
    }

    private function refuseThirdParticipant(): void
    {
        $this->components->error('Monthly memories already has two participants, and it only works with exactly two.');

        $this->line('  The participants are:');

        MemoryParticipant::with('user')
            ->orderBy('id')
            ->get()
            ->each(fn (MemoryParticipant $participant) => $this->line("    {$participant->user->email}"));
    }

    private function reportStatus(): void
    {
        if (MemoryParticipant::featureIsEnabled()) {
            $this->line('  Monthly memories is live for both participants.');

            return;
        }

        $this->line('  Monthly memories goes live once there is a second participant.');
    }
}
