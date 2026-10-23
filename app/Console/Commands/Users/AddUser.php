<?php

namespace App\Console\Commands\Users;

use App\Models\User;
use App\Notifications\Welcome;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates an account for someone and emails them a link to choose a password.
 *
 * Public registration is closed, so this is how a new person gets an account.
 * The account starts with an unguessable random password that nobody knows,
 * and is not an admin. Choosing a password from the emailed link also verifies
 * their email.
 */
class AddUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:add
            {email : Their email address, where the welcome email goes}
            {name : Their full name}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an account and email its owner a link to choose a password';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['email', 'max:255']],
        );

        if ($validator->fails()) {
            $this->components->error("[{$email}] is not a valid email address.");

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->components->error("There is already a user with the email [{$email}].");

            return self::FAILURE;
        }

        $user = User::create([
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'password' => Str::password(64),
        ]);

        $user->notify(new Welcome(Password::createToken($user)));

        $this->components->info("Created an account for {$user->email} and emailed them a link to choose a password.");

        return self::SUCCESS;
    }
}
