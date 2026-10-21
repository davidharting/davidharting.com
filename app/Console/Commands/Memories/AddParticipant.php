<?php

namespace App\Console\Commands\Memories;

use App\Models\MemoryParticipant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Makes a user one of the two memory participants, creating their account
 * first when the email has none.
 *
 * This is how participants are provisioned in production: run once per person.
 * A new account gets an unguessable password it never shares, and its owner is
 * emailed a link to choose their own password and a link to verify their
 * email, the same verification a registered user gets.
 *
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
            {--name= : Their full name. Needed only to create an account for an email that has none}
            {--display-name= : The name memories show for them, e.g. Katie. Falls back to their name}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a user one of the two monthly memories participants, creating their account if needed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::where('email', $email)->first();

        if ($user === null && ! $this->hasNameOption()) {
            $this->components->error("No user with the email [{$email}]. Pass --name to create their account.");

            return self::FAILURE;
        }

        if ($user?->memoryParticipant !== null) {
            $this->updateDisplayName($user);
            $this->components->info("{$user->email} is already a memory participant.");
            $this->reportStatus();

            return self::SUCCESS;
        }

        if (MemoryParticipant::count() >= 2) {
            $this->refuseThirdParticipant();

            return self::FAILURE;
        }

        $isNewAccount = $user === null;

        $user = DB::transaction(function () use ($user, $email): User {
            $user ??= User::create([
                'name' => $this->option('name'),
                'email' => $email,
                'password' => Str::password(64),
            ]);

            $this->updateDisplayName($user);
            MemoryParticipant::create(['user_id' => $user->id]);

            return $user;
        });

        if ($isNewAccount) {
            $this->components->info("Created an account for {$user->email}.");
            $this->emailNewAccount($user);
        }

        $this->components->info("{$user->email} is now a memory participant.");
        $this->reportStatus();

        return self::SUCCESS;
    }

    private function hasNameOption(): bool
    {
        $name = $this->option('name');

        return is_string($name) && $name !== '';
    }

    /**
     * The password link expires after an hour (`auth.passwords.users.expire`).
     * If it lapses, the forgot password page sends a fresh one, and once logged
     * in they can resend the verification email from their dashboard.
     */
    private function emailNewAccount(User $user): void
    {
        Password::sendResetLink(['email' => $user->email]);
        event(new Registered($user));

        $this->components->info("Emailed {$user->email} a link to set their password (valid for an hour) and a link to verify their email.");
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
