<?php

use App\Filament\Resources\OAuthClients\Pages\ListOAuthClients;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Once;
use Laravel\Passport\Client;
use Livewire\Livewire;
use Tests\TestCase;

test('lists each client with its count of active tokens', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);
    ['token' => $active] = bearerTokenWithRefreshToken($admin);
    ['token' => $revoked] = bearerTokenWithRefreshToken($admin);
    ['token' => $expired] = bearerTokenWithRefreshToken($admin);
    $revoked->revoke();
    $expired->forceFill(['expires_at' => now()->subDay()])->save();
    $client = $active->client;
    ['client' => $otherClient] = oauthClientWithToken($admin);

    Livewire::actingAs($admin)
        ->test(ListOAuthClients::class)
        ->assertCanSeeTableRecords([$client, $otherClient])
        ->assertTableColumnStateSet('active_tokens_count', 1, $client)
        ->assertTableColumnStateSet('active_tokens_count', 1, $otherClient);
});

describe('revoke', function () {
    test('cuts the client off and revokes everything it issued, keeping the records', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['bearer' => $bearer, 'token' => $token, 'refreshToken' => $refreshToken] = bearerTokenWithRefreshToken($admin);
        $client = $token->client;
        $authCode = oauthAuthCodeFor($client, $admin);

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
        ['token' => $revokedToken] = bearerTokenWithRefreshToken($admin);
        ['client' => $otherClient, 'token' => $otherToken, 'refreshToken' => $otherRefresh] = oauthClientWithToken($admin);

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
        ['token' => $selectedToken] = bearerTokenWithRefreshToken($admin);
        ['client' => $untouchedClient, 'token' => $untouchedToken, 'refreshToken' => $untouchedRefresh] = oauthClientWithToken($admin);

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

test('refuses a token whose client is revoked, even when the token itself is not', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);
    ['bearer' => $bearer, 'token' => $token] = bearerTokenWithRefreshToken($admin);
    $token->client->forceFill(['revoked' => true])->save();

    expect($token->fresh()->revoked)->toBeFalse();

    // Passport memoizes client lookups with once(). Issuing the token above
    // cached the client as active, and Octane flushes that cache between
    // requests, so flush it here as the next real request would see it.
    Once::flush();

    $this->withToken($bearer)->postJson('/mcp/admin', mcpToolsListRequest())->assertUnauthorized();
});

describe('delete', function () {
    test('cuts the client off and deletes it with everything it issued', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['bearer' => $bearer, 'token' => $token, 'refreshToken' => $refreshToken] = bearerTokenWithRefreshToken($admin);
        $client = $token->client;
        $authCode = oauthAuthCodeFor($client, $admin);

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
        ['token' => $selectedToken, 'refreshToken' => $selectedRefresh] = bearerTokenWithRefreshToken($admin);
        ['client' => $untouchedClient, 'token' => $untouchedToken, 'refreshToken' => $untouchedRefresh] = oauthClientWithToken($admin);
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
