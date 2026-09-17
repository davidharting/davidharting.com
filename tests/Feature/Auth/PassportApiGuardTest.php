<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/*
 * Proves the `api` guard is wired to Passport and composes with the
 * `administrate` gate. accessTokenFor() mints a real token, so these would fail
 * if User were missing Laravel\Passport\HasApiTokens.
 */

beforeEach(function () {
    Route::middleware('auth:api')->get('/test/authed', fn () => response()->json([
        'id' => auth()->id(),
        'class' => auth()->user()::class,
    ]));

    Route::middleware(['auth:api', 'can:administrate'])->get('/test/admin-only', fn () => response('welcome'));
});

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

    $this->withToken(accessTokenFor($admin))->getJson('/test/admin-only')->assertOk();
});

test('a non-admin with a valid token is forbidden', function () {
    /** @var TestCase $this */
    // The distinction that matters for /mcp/admin: authentication succeeds and
    // the token is perfectly valid, but authorization still refuses.
    $user = User::factory()->create(['is_admin' => false]);

    $this->withToken(accessTokenFor($user))->getJson('/test/admin-only')->assertForbidden();
});
