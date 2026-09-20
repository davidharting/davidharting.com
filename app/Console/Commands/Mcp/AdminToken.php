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
 * /mcp/admin authenticates a bearer token; it does not care how that token was
 * issued. Minting one directly therefore exercises the real route — auth:api,
 * the scope check, and the administrate gate — while skipping discovery,
 * consent, and the authorization code exchange, which need a browser and a
 * publicly reachable host.
 *
 * This is a convenience for driving the server by hand, not a second way in:
 * the token it produces is subject to exactly the same checks as one an MCP
 * client obtained through OAuth.
 */
class AdminToken extends Command
{
    use ConfirmableTrait;

    /**
     * The scope the admin server's route requires of a bearer token. A token
     * without it is rejected even when its owner is an admin.
     */
    private const SCOPE = 'mcp:use';

    /**
     * The name the printed registration command gives the server. Shared with
     * the matching removal command so the two cannot drift apart.
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
            {--url= : Origin the server is reachable at, e.g. http://127.0.0.1:8000. Defaults to APP_URL}
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
     * Retire the tokens this command issued previously, so that it leaves
     * exactly one live token behind.
     *
     * Reusing the previous token instead is not possible: Passport stores a
     * token's id, scopes, and expiry, but never the signed string a client
     * sends, so an issued token cannot be read back. Every run therefore mints,
     * and without revoking, long-lived admin credentials would pile up.
     *
     * Limited to tokens issued by a personal access client, which is the only
     * kind this command creates. Tokens from the authorization code flow belong
     * to real connected clients, and revoking those would sign them out.
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
     * Print the token, how to register it with a client, and how to undo both.
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

        // Unregistering the client leaves the token itself valid until it
        // expires, so print the revoke as well. Passport has no command for
        // revoking a single token, hence the inline call with the id filled in.
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
     * Minting is allowed in every environment, since a hand-issued token is a
     * reasonable way to inspect a deployed server, but only `local` does it
     * without asking.
     *
     * ConfirmableTrait's own default prompts in `production` alone, which would
     * let any environment not named that through unchallenged. Naming the
     * exemption instead of the trigger keeps staging-like environments guarded
     * however they are labelled.
     *
     * `testing` is exempt so that test runs are not blocked waiting on a
     * prompt.
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
     * The URL to tell the operator to point their MCP client at.
     *
     * A console command has no request to infer an origin from, so url() falls
     * back to APP_URL. That is frequently not where a local server is actually
     * listening — APP_URL commonly omits the port the dev server binds to —
     * which would make the printed command silently wrong. --url overrides it.
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
     * The user named on the command line, if they may reach the admin server.
     *
     * The email is a required argument rather than an inferred default because
     * this issues a credential and revokes that user's previous ones. Naming
     * the account keeps it visible in shell history and to anyone reviewing
     * what was run. Falling back to "the only admin" would also silently change
     * behaviour as soon as a second one existed.
     *
     * Authorization goes through the gate rather than the is_admin column, so
     * this cannot drift from what the rest of the application enforces.
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
     * List the accounts that would be accepted, so a mistyped address does not
     * require a separate query to recover from.
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
     * Passport refuses to issue a personal access token until a personal access
     * client exists, and a newly set up database has none. Creating it here
     * turns an opaque "Personal access client not found" runtime exception into
     * something the command handles itself.
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
