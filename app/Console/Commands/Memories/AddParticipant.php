<?php

namespace App\Console\Commands\Memories;

use App\Models\MemoryParticipant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Makes an existing user one of the two memory participants.
 *
 * This is how participants are provisioned in production: run once per person.
 * It is safe to run again, and for someone who is already a participant it only
 * updates their display name, and only when one is given.
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
            {--display-name= : The name memories show for them, e.g. Katie. Falls back to their name}
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
            $this->updateDisplayName($user);
            $this->components->info("{$user->email} is already a memory participant.");
            $this->reportStatus();

            return self::SUCCESS;
        }

        if (MemoryParticipant::count() >= 2) {
            $this->refuseThirdParticipant();

            return self::FAILURE;
        }

        $this->updateDisplayName($user);
        MemoryParticipant::create(['user_id' => $user->id]);

        $this->components->info("{$user->email} is now a memory participant.");
        $this->reportStatus();

        return self::SUCCESS;
    }

    private function updateDisplayName(User $user): void
    {
        $displayName = $this->option('display-name');

        if (! is_string($displayName) || $displayName === '') {
            return;
        }

        $user->update(['display_name' => $displayName]);
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
