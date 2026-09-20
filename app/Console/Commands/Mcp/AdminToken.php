<?php

namespace App\Console\Commands\Mcp;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
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
class AdminToken extends Command
{
    use ConfirmableTrait;

    /**
     * The scope /mcp/admin requires, hardcoded by Mcp::oauthRoutes().
     */
    private const SCOPE = 'mcp:use';

    /**
     * What the printed `claude mcp add` calls the server. Shared with the
     * matching `claude mcp remove` so the two cannot drift apart.
     */
    private const SERVER_NAME = 'mcp-admin';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:admin-token
            {email : The admin to mint the token for}
            {--name=mcp-admin : The token name, shown in the oauth_access_tokens table}
            {--url= : Where your dev server is listening. Defaults to APP_URL, which does not carry a port}
            {--force : Skip the confirmation prompt outside local}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mint an mcp:use bearer token for reaching /mcp/admin without the OAuth dance';

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
        $this->revokePreviousTokens($admin);

        $result = $admin->createToken((string) $this->option('name'), [self::SCOPE]);

        $this->components->info('Minted an '.self::SCOPE." token for {$admin->email}.");

        return $this->report($result->accessToken, $result->token->id);
    }

    /**
     * Passport cannot hand a token back: oauth_access_tokens stores the id,
     * scopes, and expiry, but never the signed string a client sends. So every
     * run mints, and the only way to avoid accumulating year-long admin
     * credentials is to retire the ones this command minted before.
     *
     * Deliberately limited to tokens issued by a personal access client, which
     * is the only kind this command creates. Tokens from the authorization code
     * flow — Claude.ai's real connection — are left alone, so running this as a
     * break-glass on a deployed environment does not disconnect the connector.
     */
    private function revokePreviousTokens(User $admin): void
    {
        $revoked = Token::query()
            ->where('user_id', $admin->getKey())
            ->whereIn('client_id', $this->personalAccessClientIds())
            ->where('revoked', false)
            ->update(['revoked' => true]);

        if ($revoked > 0) {
            $this->components->info(sprintf(
                'Revoked %d previously minted %s.',
                $revoked,
                str('token')->plural($revoked),
            ));
        }
    }

    /**
     * @return array<int, string>
     */
    private function personalAccessClientIds(): array
    {
        return Passport::client()
            ->newQuery()
            ->get()
            ->filter(fn (Client $client): bool => $client->hasGrantType('personal_access'))
            ->pluck('id')
            ->all();
    }

    /**
     * Print the token and what to do with it.
     */
    private function report(string $accessToken, string $tokenId): int
    {
        $this->line($accessToken);
        $this->newLine();

        $this->line('Connect Claude Code to it with:');
        $this->line(sprintf(
            'claude mcp add --transport http %s %s --header "Authorization: Bearer %s"',
            self::SERVER_NAME,
            $this->serverUrl(),
            $accessToken,
        ));
        $this->newLine();

        // Removing the client registration leaves the token live for a year, so
        // spell out the revoke too. Passport has no first-party command for a
        // single token, hence the tinker call with the id filled in.
        $this->line('When you are done, unregister it and revoke the token:');
        $this->line('claude mcp remove '.self::SERVER_NAME);
        $this->line(sprintf(
            'php artisan tinker --execute \'\Laravel\Passport\Token::find("%s")->revoke();\'',
            $tokenId,
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
            'Minting a real admin token for the [%s] environment, and revoking the previous ones',
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
     * The admin named on the command line.
     *
     * Taking the email as a required argument rather than inferring it is
     * deliberate: this mints a credential and retires that person's previous
     * ones, so which account it acts on belongs in the command, where shell
     * history and a reviewer can both see it. Inferring "the only admin" would
     * also change behaviour the moment a second one exists.
     */
    private function resolveAdmin(): ?User
    {
        $email = (string) $this->argument('email');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No user with the email [{$email}].");
            $this->listAdmins();

            return null;
        }

        if (Gate::forUser($user)->denies('administrate')) {
            $this->components->error("[{$email}] is not an admin, so a token for them could not reach /mcp/admin.");

            return null;
        }

        return $user;
    }

    /**
     * Offer the choices, so a typo does not mean a second trip to tinker.
     */
    private function listAdmins(): void
    {
        $admins = User::where('is_admin', true)->orderBy('id')->pluck('email');

        if ($admins->isEmpty()) {
            $this->line('  There are no admin users at all. Seed the database or promote one.');

            return;
        }

        $this->line('  Admins are:');
        $this->line($admins->map(fn (string $email): string => "    {$email}")->implode(PHP_EOL));
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
            $clients->createPersonalAccessGrantClient('MCP admin tokens', $provider);

            $this->components->info('Created the personal access client Passport needs to issue tokens.');
        }
    }
}
