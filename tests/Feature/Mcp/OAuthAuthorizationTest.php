<?php

use App\Models\User;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

/*
 * This suite validates the authorization code flow Claude runs after registering
 * a client: the consent screen at /oauth/authorize, approval, and the token
 * exchange, using the same parameters Claude sends.
 */

const PKCE_VERIFIER = 'a-test-code-verifier-that-is-at-least-43-characters-long';

function registeredClaudeClient(): Client
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Claude',
        redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
        confidential: false,
    );
}

function authorizeUrl(Client $client): string
{
    return '/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $client->id,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', PKCE_VERIFIER, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
        'state' => 'test-state',
        'scope' => 'mcp:use',
        'resource' => url('/mcp/admin'),
    ]);
}

test('a guest is sent to log in', function () {
    /** @var TestCase $this */
    $response = $this->get(authorizeUrl(registeredClaudeClient()));

    $response->assertRedirect(route('login'));
});

test('a logged-in user sees the consent screen', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)->get(authorizeUrl(registeredClaudeClient()));

    $response->assertOk();
    $response->assertSeeText('Authorize Claude');
    $response->assertSee(route('passport.authorizations.approve'));
});

test('an admin can approve, exchange the code, and reach /mcp/admin', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);
    $client = registeredClaudeClient();

    $consent = $this->actingAs($admin)->get(authorizeUrl($client));

    $approval = $this->post('/oauth/authorize', [
        'state' => '',
        'client_id' => $client->id,
        'auth_token' => $consent->viewData('authToken'),
    ]);

    $approval->assertRedirect();
    $callback = $approval->headers->get('Location');
    expect($callback)->toStartWith('https://claude.ai/api/mcp/auth_callback?');
    parse_str(parse_url($callback, PHP_URL_QUERY), $callbackQuery);
    expect($callbackQuery['state'])->toBe('test-state');

    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'code_verifier' => PKCE_VERIFIER,
        'code' => $callbackQuery['code'],
    ]);

    $token->assertOk();

    $this->app['auth']->forgetGuards();
    $this->withToken($token->json('access_token'))
        ->postJson('/mcp/admin', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertOk()
        ->assertJsonMissingPath('error');
});
