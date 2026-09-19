<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Client;
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
 * Run the command and hand back everything it printed. Artisan::call is used
 * rather than $this->artisan() because these tests read the token out of the
 * output, and PendingCommand does not record it for Artisan::output().
 *
 * @param  array<string, string>  $parameters
 * @return array{0: int, 1: string}
 */
function runDevToken(array $parameters = []): array
{
    $exitCode = Artisan::call('mcp:dev-token', $parameters);

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
    User::factory()->create(['is_admin' => true]);

    [$exitCode, $output] = runDevToken();

    expect($exitCode)->toBe(0);

    $token = tokenFromOutput($output);
    expect($token)->not->toBe('');

    $this->withToken($token)
        ->postJson('/mcp/admin', toolsListRpc())
        ->assertSuccessful();
});

test('the minted token carries only the mcp:use scope', function () {
    /** @var TestCase $this */
    User::factory()->create(['is_admin' => true]);

    runDevToken();

    expect(Token::sole()->scopes)->toBe(['mcp:use']);
});

test('a token minted without the scope is refused, which is the belt #186 leans on', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $this->withToken(accessTokenFor($admin))
        ->postJson('/mcp/admin', toolsListRpc())
        ->assertForbidden();
});

test('prints a ready-to-paste claude mcp add line', function () {
    /** @var TestCase $this */
    User::factory()->create(['is_admin' => true]);

    [, $output] = runDevToken();

    expect($output)
        ->toContain('claude mcp add --transport http')
        ->toContain(url('/mcp/admin'));
});

test('--url overrides APP_URL, which carries no port in local dev', function () {
    /** @var TestCase $this */
    User::factory()->create(['is_admin' => true]);

    [, $output] = runDevToken(['--url' => 'http://127.0.0.1:8000']);

    expect($output)->toContain('http://127.0.0.1:8000/mcp/admin');
});

test('--url tolerates a trailing slash', function () {
    /** @var TestCase $this */
    User::factory()->create(['is_admin' => true]);

    [, $output] = runDevToken(['--url' => 'http://127.0.0.1:8000/']);

    expect($output)->toContain('http://127.0.0.1:8000/mcp/admin')
        ->not->toContain('8000//mcp');
});

test('names the token from --name', function () {
    /** @var TestCase $this */
    User::factory()->create(['is_admin' => true]);

    runDevToken(['--name' => 'scratch']);

    expect(Token::sole()->name)->toBe('scratch');
});

describe('the personal access client', function () {
    test('is created when there is not one yet', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true]);

        expect(Client::count())->toBe(0);

        [$exitCode, $output] = runDevToken();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('Created the personal access client')
            ->and(Client::count())->toBe(1);
    });

    test('is reused on a second run rather than duplicated', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true]);

        runDevToken();
        runDevToken();

        expect(Client::count())->toBe(1)
            ->and(Token::count())->toBe(2);
    });
});

describe('choosing the admin', function () {
    test('fails when there are no admins', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => false]);

        [$exitCode, $output] = runDevToken();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('There are no admin users')
            ->and(Token::count())->toBe(0);
    });

    test('fails and lists them when there is more than one admin', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true, 'email' => 'first@example.test']);
        User::factory()->create(['is_admin' => true, 'email' => 'second@example.test']);

        [$exitCode, $output] = runDevToken();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('There is more than one admin')
            ->and($output)->toContain('first@example.test')
            ->and(Token::count())->toBe(0);
    });

    test('--email picks one when there are several', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true, 'email' => 'first@example.test']);
        $second = User::factory()->create(['is_admin' => true, 'email' => 'second@example.test']);

        [$exitCode] = runDevToken(['--email' => 'second@example.test']);

        expect($exitCode)->toBe(0)
            ->and(Token::sole()->user_id)->toBe($second->id);
    });

    test('refuses to mint for a non-admin', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => false, 'email' => 'frodo@example.test']);

        [$exitCode, $output] = runDevToken(['--email' => 'frodo@example.test']);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('is not an admin')
            ->and(Token::count())->toBe(0);
    });

    test('fails on an unknown email', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true]);

        [$exitCode, $output] = runDevToken(['--email' => 'nobody@example.test']);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('No user with the email')
            ->and(Token::count())->toBe(0);
    });
});

describe('the confirmation guard', function () {
    test('mints without a prompt in local', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true]);
        $this->app['env'] = 'local';

        $this->artisan('mcp:dev-token')->assertSuccessful();

        expect(Token::count())->toBe(1);
    });

    test('asks first outside local, and mints nothing when declined', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true]);
        $this->app['env'] = 'production';

        $this->artisan('mcp:dev-token')
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();

        expect(Token::count())->toBe(0);
    });

    test('mints outside local once confirmed', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true]);
        $this->app['env'] = 'production';

        $this->artisan('mcp:dev-token')
            ->expectsConfirmation('Are you sure you want to run this command?', 'yes')
            ->assertSuccessful();

        expect(Token::count())->toBe(1);
    });

    // The alert component renders its content uppercase, and pipes the
    // interpolated environment name through EnsureDynamicContentIsHighlighted,
    // which wraps it in styling this assertion cannot see. Pin the sentence
    // stem, which is what tells the operator what is about to happen.
    test('warns about what it is about to do before asking', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true]);
        $this->app['env'] = 'staging';

        $this->artisan('mcp:dev-token')
            ->expectsOutputToContain('MINTING A REAL ADMIN TOKEN FOR THE')
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();
    });

    test('--force skips the prompt outside local', function () {
        /** @var TestCase $this */
        User::factory()->create(['is_admin' => true]);
        $this->app['env'] = 'production';

        $this->artisan('mcp:dev-token', ['--force' => true])->assertSuccessful();

        expect(Token::count())->toBe(1);
    });
});
