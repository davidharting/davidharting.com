<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

/*
 * Proves the `api` guard is wired to Passport and composes with the
 * `administrate` gate — the combination /mcp/admin will sit behind (#187).
 *
 * These mint a real access token rather than using Passport::actingAs(), which
 * bypasses TokenGuard entirely. Only a real token exercises the full path,
 * including User::withAccessToken() — the method that would fatal if the model
 * were missing Laravel\Passport\HasApiTokens.
 */

beforeEach(function () {
    Route::middleware('auth:api')->get('/test/authed', fn () => response()->json([
        'id' => auth()->id(),
        'class' => auth()->user()::class,
    ]));

    Route::middleware(['auth:api', 'can:administrate'])->get('/test/admin-only', fn () => response('welcome'));
});

/** Issue a genuine bearer token for the given user. */
function accessTokenFor(User $user): string
{
    app(ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client');

    return $user->createToken('test-token')->accessToken;
}

test('a valid bearer token resolves to the user through the api guard', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this->withToken(accessTokenFor($user))->getJson('/test/authed');

    $response->assertOk();
    $response->assertJsonPath('id', $user->id);
    // The guard must hand back an App\Models\User, not a bare Authenticatable —
    // the administrate gate closure type-hints App\Models\User.
    $response->assertJsonPath('class', User::class);
});

test('a request with no token is rejected', function () {
    /** @var TestCase $this */
    $this->getJson('/test/authed')->assertUnauthorized();
});

test('a garbage token is rejected', function () {
    /** @var TestCase $this */
    $this->withToken('not-a-real-token')->getJson('/test/authed')->assertUnauthorized();
});

test('an admin passes the administrate gate over the api guard', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withToken(accessTokenFor($admin))->getJson('/test/admin-only');

    $response->assertOk();
});

test('a non-admin with a valid token is forbidden', function () {
    /** @var TestCase $this */
    // The distinction that matters for /mcp/admin: authentication succeeds and
    // the token is perfectly valid, but authorization still refuses.
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->withToken(accessTokenFor($user))->getJson('/test/admin-only');

    $response->assertForbidden();
});
