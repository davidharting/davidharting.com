<?php

use App\Enum\OAuthTokenStatus;
use App\Filament\Resources\OAuthTokens\Pages\ListOAuthTokens;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\TestCase;

test('lists tokens with their client and user', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);
    ['token' => $token] = bearerTokenWithRefreshToken($admin);

    Livewire::actingAs($admin)
        ->test(ListOAuthTokens::class)
        ->assertCanSeeTableRecords([$token])
        ->assertTableColumnStateSet('client.name', 'Test Personal Access Client', $token)
        ->assertTableColumnStateSet('user_email', $admin->email, $token)
        ->assertTableColumnStateSet('status', OAuthTokenStatus::Active, $token);
});

test('finds tokens by user email', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true, 'email' => 'david@example.com']);
    $other = User::factory()->create(['is_admin' => true, 'email' => 'someone@example.com']);
    ['token' => $davidsToken] = bearerTokenWithRefreshToken($admin);
    ['token' => $othersToken] = bearerTokenWithRefreshToken($other);

    Livewire::actingAs($admin)
        ->test(ListOAuthTokens::class)
        ->searchTable('david@')
        ->assertCanSeeTableRecords([$davidsToken])
        ->assertCanNotSeeTableRecords([$othersToken]);
});

test('filters tokens by status', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);
    ['token' => $active] = bearerTokenWithRefreshToken($admin);
    ['token' => $revoked] = bearerTokenWithRefreshToken($admin);
    ['token' => $expired] = bearerTokenWithRefreshToken($admin);
    $revoked->revoke();
    $expired->forceFill(['expires_at' => now()->subDay()])->save();

    $page = Livewire::actingAs($admin)->test(ListOAuthTokens::class);

    $page->filterTable('status', 'active')
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$revoked, $expired]);

    $page->filterTable('status', 'revoked')
        ->assertCanSeeTableRecords([$revoked])
        ->assertCanNotSeeTableRecords([$active, $expired]);

    $page->filterTable('status', 'expired')
        ->assertCanSeeTableRecords([$expired])
        ->assertCanNotSeeTableRecords([$active, $revoked]);
});

describe('revoke', function () {
    test('cuts the token off and revokes its refresh token, keeping both records', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['bearer' => $bearer, 'token' => $token, 'refreshToken' => $refreshToken] = bearerTokenWithRefreshToken($admin);

        Livewire::actingAs($admin)
            ->test(ListOAuthTokens::class)
            ->callAction(TestAction::make('revoke')->table($token))
            ->assertNotified();

        expect($token->fresh()->revoked)->toBeTrue()
            ->and($refreshToken->fresh()->revoked)->toBeTrue();

        $this->withToken($bearer)->postJson('/mcp/admin', mcpToolsListRequest())->assertUnauthorized();
    });

    test('is not offered for a token that is already revoked', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['token' => $token] = bearerTokenWithRefreshToken($admin);
        $token->revoke();

        Livewire::actingAs($admin)
            ->test(ListOAuthTokens::class)
            ->assertActionHidden(TestAction::make('revoke')->table($token));
    });

    test('in bulk revokes only the selected tokens', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['token' => $selected, 'refreshToken' => $selectedRefresh] = bearerTokenWithRefreshToken($admin);
        ['token' => $untouched, 'refreshToken' => $untouchedRefresh] = bearerTokenWithRefreshToken($admin);

        Livewire::actingAs($admin)
            ->test(ListOAuthTokens::class)
            ->selectTableRecords([$selected])
            ->callAction(TestAction::make('revoke')->table()->bulk());

        expect($selected->fresh()->revoked)->toBeTrue()
            ->and($selectedRefresh->fresh()->revoked)->toBeTrue()
            ->and($untouched->fresh()->revoked)->toBeFalse()
            ->and($untouchedRefresh->fresh()->revoked)->toBeFalse();
    });
});

describe('delete', function () {
    test('cuts the token off and deletes it with its refresh token', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['bearer' => $bearer, 'token' => $token, 'refreshToken' => $refreshToken] = bearerTokenWithRefreshToken($admin);

        Livewire::actingAs($admin)
            ->test(ListOAuthTokens::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($token))
            ->assertNotified();

        expect($token->fresh())->toBeNull()
            ->and($refreshToken->fresh())->toBeNull();

        $this->withToken($bearer)->postJson('/mcp/admin', mcpToolsListRequest())->assertUnauthorized();
    });

    test('in bulk deletes only the selected tokens', function () {
        /** @var TestCase $this */
        $admin = User::factory()->create(['is_admin' => true]);
        ['token' => $selected, 'refreshToken' => $selectedRefresh] = bearerTokenWithRefreshToken($admin);
        ['token' => $untouched, 'refreshToken' => $untouchedRefresh] = bearerTokenWithRefreshToken($admin);

        Livewire::actingAs($admin)
            ->test(ListOAuthTokens::class)
            ->selectTableRecords([$selected])
            ->callAction(TestAction::make('delete')->table()->bulk());

        expect($selected->fresh())->toBeNull()
            ->and($selectedRefresh->fresh())->toBeNull()
            ->and($untouched->fresh())->not->toBeNull()
            ->and($untouchedRefresh->fresh())->not->toBeNull();
    });
});

test('is closed to non-admins', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin/oauth-tokens')->assertForbidden();
});
