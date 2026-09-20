<?php

use App\Http\Middleware\RestrictMcpConsentToAdmins;
use App\Models\User;
use Illuminate\Session\Middleware\StartSession;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

/*
 * Covers RestrictMcpConsentToAdmins: the consent-time half of keeping `mcp:use`
 * tokens away from non-admins (issue #186). The resource-time half is
 * CheckToken::using('mcp:use') plus can:administrate on /mcp/admin, covered by
 * the AdminServer tests.
 *
 * Only the admin case needs a real oauth_clients row. The middleware runs ahead
 * of validateAuthorizationRequest(), so a rejection happens before league would
 * object to a bogus client — which is the point of asserting it that way here.
 */

const CONSENT_PKCE_VERIFIER = 'a-test-code-verifier-that-is-at-least-43-characters-long';

function consentUrl(string $scope, ?Client $client = null): string
{
    return '/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $client?->id ?? 'no-such-client',
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', CONSENT_PKCE_VERIFIER, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
        'state' => 'test-state',
        'scope' => $scope,
    ]);
}

function claudeClient(): Client
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Claude',
        redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
        confidential: false,
    );
}

test('a non-admin asking for mcp:use is refused', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)->get(consentUrl('mcp:use'));

    $response->assertForbidden();
    $response->assertSeeText('This account is not permitted to connect to the MCP server.');
});

test('an admin asking for mcp:use reaches the consent screen', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)->get(consentUrl('mcp:use', claudeClient()));

    $response->assertOk();
    $response->assertSeeText('Authorize Claude');
});

test('a guest is sent to log in rather than refused', function () {
    /** @var TestCase $this */
    $response = $this->get(consentUrl('mcp:use', claudeClient()));

    $response->assertRedirect(route('login'));
});

test('a non-admin not asking for mcp:use is left alone', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)->get(consentUrl('', claudeClient()));

    $response->assertOk();
    $response->assertSeeText('Authorize Claude');
});

test('the token endpoint is untouched', function () {
    /** @var TestCase $this */
    $response = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => claudeClient()->id,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'code_verifier' => CONSENT_PKCE_VERIFIER,
        'code' => 'not-a-real-authorization-code',
    ]);

    // Rejected by league for the bogus code, never by our 403.
    $response->assertStatus(400);
});

test('the middleware runs after the session has started', function () {
    /** @var TestCase $this */
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->firstOrFail(fn ($route) => $route->getName() === 'passport.authorizations.authorize');

    $stack = array_values(array_map(
        fn ($middleware) => is_string($middleware) ? $middleware : $middleware::class,
        app('router')->gatherRouteMiddleware($route),
    ));

    expect(array_search(RestrictMcpConsentToAdmins::class, $stack, strict: true))
        ->toBeGreaterThan(array_search(StartSession::class, $stack, strict: true));
})->note(<<<'NOTE'
    This is asserted structurally because no behavioural test can catch it.

    config('passport.middleware') attaches the middleware to Passport's route
    group, and group middleware is absent from $middlewarePriority, so it sorts
    to the FRONT of the stack by default -- ahead of StartSession, where
    $request->user() is always null. The middleware then takes its guest branch
    and waves every request through, including a logged-in non-admin's.

    A feature test cannot see that: actingAs() puts the user on the guard
    directly, and both the guard and the session store are container singletons
    that outlive a single request inside one test process, so even a test that
    logs in over the session passes with the ordering broken. It was caught by
    driving a real browser-style request against `php artisan serve`, which
    returned 200 and the consent screen where this expects 403.

    bootstrap/app.php pins the order with appendToPriorityList().
    NOTE);
