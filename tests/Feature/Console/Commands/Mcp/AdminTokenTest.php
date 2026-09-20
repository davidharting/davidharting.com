<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;
use Tests\TestCase;

function toolsListRpc(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ];
}

/**
 * Run the command and hand back its exit code and everything it printed.
 *
 * Artisan::call is used rather than $this->artisan() because these tests read
 * the token out of the output, and the pending-command helper does not record
 * output where Artisan::output() can see it.
 *
 * @param  array<string, string>  $parameters
 * @return array{0: int, 1: string}
 */
function runAdminToken(string $email, array $parameters = []): array
{
    $exitCode = Artisan::call('mcp:admin-token', ['email' => $email] + $parameters);

    return [$exitCode, Artisan::output()];
}

/**
 * The command prints the raw token on its own line.
 */
function tokenFromOutput(string $output): string
{
    preg_match('/\beyJ[\w-]+\.[\w-]+\.[\w-]+/', $output, $matches);

    return $matches[0] ?? '';
}

test('mints a token that reaches /mcp/admin', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    [$exitCode, $output] = runAdminToken($admin->email);

    expect($exitCode)->toBe(0);

    $token = tokenFromOutput($output);
    expect($token)->not->toBe('');

    $this->withToken($token)
        ->postJson('/mcp/admin', toolsListRpc())
        ->assertSuccessful();
});

test('the minted token carries only the mcp:use scope', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    runAdminToken($admin->email);

    expect(Token::sole()->scopes)->toBe(['mcp:use']);
});

test('a token without the mcp:use scope is refused', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $this->withToken(accessTokenFor($admin))
        ->postJson('/mcp/admin', toolsListRpc())
        ->assertForbidden();
});

test('prints a ready-to-paste claude mcp add line', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    [, $output] = runAdminToken($admin->email);

    expect($output)
        ->toContain('claude mcp add --transport http')
        ->toContain(url('/mcp/admin'));
});

test('--url overrides APP_URL, which carries no port in local dev', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    [, $output] = runAdminToken($admin->email, ['--url' => 'http://127.0.0.1:8000']);

    expect($output)->toContain('http://127.0.0.1:8000/mcp/admin');
});

test('--url tolerates a trailing slash', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    [, $output] = runAdminToken($admin->email, ['--url' => 'http://127.0.0.1:8000/']);

    expect($output)->toContain('http://127.0.0.1:8000/mcp/admin')
        ->not->toContain('8000//mcp');
});

test('prints how to clean up afterwards', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    [, $output] = runAdminToken($admin->email);

    expect($output)
        ->toContain('claude mcp remove mcp-admin')
        ->toContain('->revoke();')
        ->toContain(Token::sole()->id);
});

test('the add and remove lines name the same server', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    [, $output] = runAdminToken($admin->email);

    preg_match('/claude mcp add --transport http (\\S+)/', $output, $added);
    preg_match('/claude mcp remove (\\S+)/', $output, $removed);

    expect($added[1])->toBe($removed[1]);
});

test('names the token from --name', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    runAdminToken($admin->email, ['--name' => 'scratch']);

    expect(Token::sole()->name)->toBe('scratch');
});

describe('the personal access client', function () {
    test('is created when there is not one yet', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        expect(Client::count())->toBe(0);

        [$exitCode, $output] = runAdminToken($admin->email);

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('Created the personal access client')
            ->and(Client::count())->toBe(1);
    });

    test('is reused on a second run rather than duplicated', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        runAdminToken($admin->email);
        runAdminToken($admin->email);

        expect(Client::count())->toBe(1);
    });
});

describe('choosing the admin', function () {
    test('mints for the admin named on the command line', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true, 'email' => 'first@example.test']);
        $second = User::factory()->create(['is_admin' => true, 'email' => 'second@example.test']);

        [$exitCode] = runAdminToken('second@example.test');

        expect($exitCode)->toBe(0)
            ->and(Token::sole()->user_id)->toBe($second->id);
    });

    test('an unknown email lists the admins to choose from', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true, 'email' => 'first@example.test']);
        User::factory()->create(['is_admin' => true, 'email' => 'second@example.test']);
        User::factory()->create(['is_admin' => false, 'email' => 'frodo@example.test']);

        [$exitCode, $output] = runAdminToken('typo@example.test');

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('No user with the email')
            ->and($output)->toContain('first@example.test')
            ->and($output)->toContain('second@example.test')
            ->and($output)->not->toContain('frodo@example.test')
            ->and(Token::count())->toBe(0);
    });

    test('says so when there are no admins at all', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => false, 'email' => 'frodo@example.test']);

        [$exitCode, $output] = runAdminToken('nobody@example.test');

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('There are no admin users at all')
            ->and(Token::count())->toBe(0);
    });

    test('refuses to mint for a non-admin', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => false, 'email' => 'frodo@example.test']);

        [$exitCode, $output] = runAdminToken('frodo@example.test');

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('is not an admin')
            ->and(Token::count())->toBe(0);
    });

    test('the email is required, so it never guesses', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true]);

        expect(fn () => Artisan::call('mcp:admin-token'))
            ->toThrow(RuntimeException::class, 'Not enough arguments');

        expect(Token::count())->toBe(0);
    });
});

describe('the confirmation guard', function () {
    test('mints without a prompt in local', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $this->app['env'] = 'local';

        $this->artisan('mcp:admin-token', ['email' => $admin->email])->assertSuccessful();

        expect(Token::count())->toBe(1);
    });

    test('asks first outside local, and mints nothing when declined', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $this->app['env'] = 'production';

        $this->artisan('mcp:admin-token', ['email' => $admin->email])
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();

        expect(Token::count())->toBe(0);
    });

    test('mints outside local once confirmed', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $this->app['env'] = 'production';

        $this->artisan('mcp:admin-token', ['email' => $admin->email])
            ->expectsConfirmation('Are you sure you want to run this command?', 'yes')
            ->assertSuccessful();

        expect(Token::count())->toBe(1);
    });

    // The alert component uppercases its content and wraps any bracketed value
    // in styling that output assertions cannot match, so assert on the fixed
    // part of the sentence rather than the interpolated environment name.
    test('warns about what it is about to do before asking', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $this->app['env'] = 'staging';

        $this->artisan('mcp:admin-token', ['email' => $admin->email])
            ->expectsOutputToContain('MINTING A REAL ADMIN TOKEN FOR THE')
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();
    });

    test('--force skips the prompt outside local', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $this->app['env'] = 'production';

        $this->artisan('mcp:admin-token', ['email' => $admin->email, '--force' => true])->assertSuccessful();

        expect(Token::count())->toBe(1);
    });
});

describe('only one live token', function () {
    test('each run leaves exactly one live token behind', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        runAdminToken($admin->email);
        runAdminToken($admin->email);
        runAdminToken($admin->email);

        expect(Token::count())->toBe(3)
            ->and(Token::where('revoked', false)->count())->toBe(1);
    });

    test('sweeps up several stale tokens at once', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        accessTokenFor($admin, ['mcp:use']);
        accessTokenFor($admin, ['mcp:use']);

        [, $output] = runAdminToken($admin->email);

        expect($output)->toContain('Revoked 2 previously minted tokens.')
            ->and(Token::where('revoked', false)->count())->toBe(1);
    });

    test('the newest token is the live one and it works', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        runAdminToken($admin->email);
        [, $output] = runAdminToken($admin->email);

        $this->withToken(tokenFromOutput($output))
            ->postJson('/mcp/admin', toolsListRpc())
            ->assertSuccessful();

        // A JWT's jti claim is the token's id, so this pins that the token the
        // command printed is the one row left unrevoked.
        $claims = json_decode(base64_decode(explode('.', tokenFromOutput($output))[1]), true);

        expect(Token::where('revoked', false)->sole()->id)->toBe($claims['jti']);
    });

    test('the previous token stops working', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        [, $first] = runAdminToken($admin->email);
        runAdminToken($admin->email);

        $this->withToken(tokenFromOutput($first))
            ->postJson('/mcp/admin', toolsListRpc())
            ->assertUnauthorized();
    });

    test('says nothing about revoking on the first run', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        [, $output] = runAdminToken($admin->email);

        expect($output)->not->toContain('previously minted');
    });

    test('counts one token in the singular', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        runAdminToken($admin->email);
        [, $output] = runAdminToken($admin->email);

        expect($output)->toContain('Revoked 1 previously minted token.');
    });

    test('leaves another admin\'s token alone', function () {
        /** @var TestCase $this */
        $first = User::factory()->create(['is_admin' => true, 'email' => 'first@example.test']);
        User::factory()->create(['is_admin' => true, 'email' => 'second@example.test']);

        runAdminToken('first@example.test');
        runAdminToken('second@example.test');

        expect(Token::where('user_id', $first->id)->where('revoked', false)->count())->toBe(1);
    });

    test('leaves authorization-code tokens alone, so Claude.ai stays connected', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);

        $claude = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: 'Claude',
            redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
            confidential: false,
        );
        $connected = Token::create([
            'id' => 'a-token-from-the-oauth-dance',
            'user_id' => $admin->getKey(),
            'client_id' => $claude->getKey(),
            'scopes' => ['mcp:use'],
            'revoked' => false,
            'expires_at' => now()->addYear(),
        ]);

        runAdminToken($admin->email);
        runAdminToken($admin->email);

        expect($connected->fresh()->revoked)->toBeFalse();
    });
});
