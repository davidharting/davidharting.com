<?php

namespace App\Console\Commands\Mcp;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\ClientRepository;
use RuntimeException;

/**
 * Mints a bearer token for /mcp/admin without the browser OAuth dance.
 *
 * This is the "tier 2" development loop decided on issue #188: the token goes
 * through the same auth:api, CheckToken, and can:administrate middleware that
 * Claude.ai's token does, so it exercises the real stack while skipping
 * discovery, consent, and the code exchange.
 *
 * @see https://github.com/davidharting/davidharting.com/issues/188
 */
class DevToken extends Command
{
    use ConfirmableTrait;

    /**
     * The scope /mcp/admin requires, hardcoded by Mcp::oauthRoutes().
     */
    private const SCOPE = 'mcp:use';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:dev-token
            {--email= : The admin to mint for. Only needed when there is more than one}
            {--name=local-mcp : The token name, shown in the oauth_access_tokens table}
            {--url= : Where your dev server is listening. Defaults to APP_URL, which does not carry a port}
            {--force : Skip the confirmation prompt in production}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mint an mcp:use bearer token for local development against /mcp/admin';

    /**
     * Execute the console command.
     */
    public function handle(ClientRepository $clients): int
    {
        if (! $this->confirmToProceed($this->confirmationWarning(), $this->needsConfirmation(...))) {
            return self::FAILURE;
        }

        $admin = $this->resolveAdmin();

        if ($admin === null) {
            return self::FAILURE;
        }

        $this->ensurePersonalAccessClient($clients);

        $token = $admin->createToken((string) $this->option('name'), [self::SCOPE])->accessToken;

        $this->newLine();
        $this->components->info('Minted an '.self::SCOPE." token for {$admin->email}.");
        $this->line($token);
        $this->newLine();
        $this->line('Connect Claude Code to it with:');
        $this->line(sprintf(
            'claude mcp add --transport http mcp-admin-local %s --header "Authorization: Bearer %s"',
            $this->serverUrl(),
            $token,
        ));
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Minting is allowed everywhere — a hand-minted token is a reasonable
     * break-glass for debugging a preview or production — but only `local` gets
     * to do it silently. ConfirmableTrait's own default would ask in
     * `production` alone, which would wave through any environment that is
     * merely not named that.
     *
     * `testing` is exempt so the suite is not answering prompts; the guard's
     * behaviour outside `local` is covered by tests that set the environment.
     */
    private function needsConfirmation(): bool
    {
        return ! $this->getLaravel()->environment('local', 'testing');
    }

    private function confirmationWarning(): string
    {
        return sprintf(
            'Minting a real admin token for the [%s] environment',
            $this->getLaravel()->environment(),
        );
    }

    /**
     * Where to tell the developer to point their MCP client.
     *
     * url() is unreliable here: in a console command there is no request to
     * infer the origin from, so it falls back to APP_URL, which this project
     * sets to http://localhost with no port while the dev server listens on
     * 8000 (pitchfork.toml). Hence --url.
     */
    private function serverUrl(): string
    {
        $url = $this->option('url');

        if (is_string($url) && $url !== '') {
            return rtrim($url, '/').'/mcp/admin';
        }

        return url('/mcp/admin');
    }

    /**
     * Find the admin to mint for, reporting the reason when there is no single
     * obvious answer rather than guessing at one.
     */
    private function resolveAdmin(): ?User
    {
        $email = $this->option('email');

        if (is_string($email) && $email !== '') {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                $this->components->error("No user with the email [{$email}].");

                return null;
            }

            if (Gate::forUser($user)->denies('administrate')) {
                $this->components->error("[{$email}] is not an admin, so a token for them could not reach /mcp/admin.");

                return null;
            }

            return $user;
        }

        $admins = User::where('is_admin', true)->orderBy('id')->get();

        if ($admins->isEmpty()) {
            $this->components->error('There are no admin users. Seed the database or promote one, then try again.');

            return null;
        }

        if ($admins->count() > 1) {
            $this->components->error('There is more than one admin. Pass --email to pick one:');
            $this->line($admins->pluck('email')->map(fn (string $email): string => "  {$email}")->implode(PHP_EOL));

            return null;
        }

        return $admins->first();
    }

    /**
     * Passport needs a personal access client before it will issue a token, and
     * a fresh checkout has none. Create one rather than making the developer
     * decode "Personal access client not found for 'users' user provider."
     */
    private function ensurePersonalAccessClient(ClientRepository $clients): void
    {
        $provider = (string) config('auth.guards.api.provider');

        try {
            $clients->personalAccessClient($provider);
        } catch (RuntimeException) {
            $clients->createPersonalAccessGrantClient('MCP development', $provider);

            $this->components->info('Created the personal access client Passport needs to issue tokens.');
        }
    }
}
