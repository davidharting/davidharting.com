<?php

use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Tests\TestCase;

/*
 * This suite validates the behavior of POST /oauth/register, which is used for
 * dynamic client registration. laravel/mcp registers it in Mcp::oauthRoutes().
 */

const CLAUDE_REDIRECT_URI = 'https://claude.ai/api/mcp/auth_callback';

function registerClient(array $redirectUris, string $name = 'Claude'): TestResponse
{
    return test()->postJson('/oauth/register', [
        'client_name' => $name,
        'redirect_uris' => $redirectUris,
    ]);
}

test('a client redirecting to claude.ai is registered', function () {
    /** @var TestCase $this */
    $response = registerClient([CLAUDE_REDIRECT_URI]);

    $response->assertCreated();
    $response->assertJsonPath('redirect_uris', [CLAUDE_REDIRECT_URI]);
    $response->assertJsonPath('scope', 'mcp:use');

    // A public client: no secret to distribute, which is why PKCE is mandatory.
    $response->assertJsonPath('token_endpoint_auth_method', 'none');

    $client = Client::findOrFail($response->json('client_id'));
    expect($client->redirect_uris)->toBe([CLAUDE_REDIRECT_URI]);
});

test('a client redirecting anywhere else is rejected', function (string $redirectUri) {
    /** @var TestCase $this */
    $response = registerClient([$redirectUri], 'Not Claude');

    $response->assertBadRequest();
    $response->assertJsonPath('error', 'invalid_redirect_uri');
    expect(Client::count())->toBe(0);
})->with([
    'an unrelated host' => 'https://evil.test/cb',
    // Prefix matching without the trailing slash would let this through.
    'a lookalike host' => 'https://claude.ai.evil.test/cb',
    // Same origin over http is still not the origin we allowed.
    'plain http' => 'http://claude.ai/api/mcp/auth_callback',
    // Claude Code needs port-agnostic loopback matching, which Passport lacks.
    'a loopback address' => 'http://localhost:33418/callback',
]);

test('registration is rate limited', function () {
    /** @var TestCase $this */
    // Fails if the throttled route in routes/ai.php stops replacing the package's.
    for ($i = 0; $i < 10; $i++) {
        registerClient([CLAUDE_REDIRECT_URI])->assertCreated();
    }

    registerClient([CLAUDE_REDIRECT_URI])->assertTooManyRequests();
});
