<?php

use App\Filament\Resources\OAuthClients\Pages\ListOAuthClients;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;
use Laravel\Passport\AuthCode;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A pending authorization code for the client, the one dependent row a token
 * never covers.
 */
function authCodeFor(Client $client, User $user): AuthCode
{
    return Passport::authCode()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->getKey(),
        'client_id' => $client->getKey(),
        'scopes' => '["mcp:use"]',
        'revoked' => false,
        'expires_at' => now()->addMinutes(10),
    ]);
}

/**
 * A second client with a token and refresh token of its own. The token is a
 * bare row, not a usable bearer: these tests only check it is left alone.
 *
 * @return array{client: Client, token: Token, refreshToken: RefreshToken}
 */
function otherClientWithToken(User $user): array
{
    $client = Client::factory()->create();

    $token = Passport::token()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->getKey(),
        'client_id' => $client->getKey(),
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'expires_at' => now()->addYear(),
    ]);

    $refreshToken = Passport::refreshToken()->forceCreate([
        'id' => Str::random(80),
        'access_token_id' => $token->getKey(),
        'revoked' => false,
        'expires_at' => now()->addYear(),
    ]);

    return ['client' => $client, 'token' => $token, 'refreshToken' => $refreshToken];
}

test('lists clients with how many active tokens each holds', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);
    ['token' => $active] = adminTokenWithRefreshToken($admin);
    $client = $active->client;
    $revoked = $admin->createToken('revoked', ['mcp:use'])->token;
    $revoked->revoke();

    Livewire::actingAs($admin)
        ->test(ListOAuthClients::class)
        ->assertCanSeeTableRecords([$client])
        ->assertTableColumnStateSet('active_tokens_count', 1, $client);
});

describe('revoke', function () {
    test('cuts the client off and revokes everything it issued, keeping the records', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['bearer' => $bearer, 'token' => $token, 'refreshToken' => $refreshToken] = adminTokenWithRefreshToken($admin);
        $client = $token->client;
        $authCode = authCodeFor($client, $admin);

        Livewire::actingAs($admin)
            ->test(ListOAuthClients::class)
            ->callAction(TestAction::make('revoke')->table($client))
            ->assertNotified();

        expect($client->fresh()->revoked)->toBeTrue()
            ->and($token->fresh()->revoked)->toBeTrue()
            ->and($refreshToken->fresh()->revoked)->toBeTrue()
            ->and($authCode->fresh()->revoked)->toBeTrue();

        $this->withToken($bearer)->postJson('/mcp/admin', mcpToolsListRequest())->assertUnauthorized();
    });

    test('leaves other clients and their tokens alone', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['token' => $revokedToken] = adminTokenWithRefreshToken($admin);
        ['client' => $otherClient, 'token' => $otherToken, 'refreshToken' => $otherRefresh] = otherClientWithToken($admin);

        Livewire::actingAs($admin)
            ->test(ListOAuthClients::class)
            ->callAction(TestAction::make('revoke')->table($revokedToken->client));

        expect($otherClient->fresh()->revoked)->toBeFalse()
            ->and($otherToken->fresh()->revoked)->toBeFalse()
            ->and($otherRefresh->fresh()->revoked)->toBeFalse();
    });

    test('is not offered for a client that is already revoked', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        $client = Client::factory()->create(['revoked' => true]);

        Livewire::actingAs($admin)
            ->test(ListOAuthClients::class)
            ->assertActionHidden(TestAction::make('revoke')->table($client));
    });

    test('in bulk revokes only the selected clients', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['token' => $selectedToken] = adminTokenWithRefreshToken($admin);
        ['client' => $untouchedClient, 'token' => $untouchedToken] = otherClientWithToken($admin);

        Livewire::actingAs($admin)
            ->test(ListOAuthClients::class)
            ->selectTableRecords([$selectedToken->client])
            ->callAction(TestAction::make('revoke')->table()->bulk());

        expect($selectedToken->client->fresh()->revoked)->toBeTrue()
            ->and($selectedToken->fresh()->revoked)->toBeTrue()
            ->and($untouchedClient->fresh()->revoked)->toBeFalse()
            ->and($untouchedToken->fresh()->revoked)->toBeFalse();
    });
});

describe('delete', function () {
    test('cuts the client off and deletes it with everything it issued', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['bearer' => $bearer, 'token' => $token, 'refreshToken' => $refreshToken] = adminTokenWithRefreshToken($admin);
        $client = $token->client;
        $authCode = authCodeFor($client, $admin);

        Livewire::actingAs($admin)
            ->test(ListOAuthClients::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($client))
            ->assertNotified();

        expect($client->fresh())->toBeNull()
            ->and($token->fresh())->toBeNull()
            ->and($refreshToken->fresh())->toBeNull()
            ->and($authCode->fresh())->toBeNull();

        $this->withToken($bearer)->postJson('/mcp/admin', mcpToolsListRequest())->assertUnauthorized();
    });

    test('in bulk deletes only the selected clients', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['token' => $selectedToken, 'refreshToken' => $selectedRefresh] = adminTokenWithRefreshToken($admin);
        ['client' => $untouchedClient, 'token' => $untouchedToken, 'refreshToken' => $untouchedRefresh] = otherClientWithToken($admin);
        $selectedClient = $selectedToken->client;

        Livewire::actingAs($admin)
            ->test(ListOAuthClients::class)
            ->selectTableRecords([$selectedClient])
            ->callAction(TestAction::make('delete')->table()->bulk());

        expect($selectedClient->fresh())->toBeNull()
            ->and($selectedToken->fresh())->toBeNull()
            ->and($selectedRefresh->fresh())->toBeNull()
            ->and($untouchedClient->fresh())->not->toBeNull()
            ->and($untouchedToken->fresh())->not->toBeNull()
            ->and($untouchedRefresh->fresh())->not->toBeNull();
    });
});

test('is closed to non-admins', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin/oauth-clients')->assertForbidden();
});
