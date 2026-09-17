<?php

use Tests\TestCase;

/*
 * The .well-known documents registered by Mcp::oauthRoutes(). Claude reads these
 * to find our authorization, token, and registration endpoints. If a URL in them
 * is wrong, Claude cannot connect to /mcp/admin.
 */

test('the nested protected-resource document describes the admin server', function () {
    /** @var TestCase $this */
    $response = $this->getJson('/.well-known/oauth-protected-resource/mcp/admin');

    $response->assertOk();
    $response->assertJsonPath('resource', url('/mcp/admin'));
    $response->assertJsonPath('authorization_servers.0', url('/'));
    $response->assertJsonPath('scopes_supported.0', 'mcp:use');
});

test('the authorization-server document points at this app', function () {
    /** @var TestCase $this */
    $response = $this->getJson('/.well-known/oauth-authorization-server');

    $response->assertOk();
    $response->assertJsonPath('issuer', url('/'));
    $response->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'));
    $response->assertJsonPath('token_endpoint', route('passport.token'));
    $response->assertJsonPath('registration_endpoint', url('/oauth/register'));

    // Claude.ai requires PKCE with S256, and the package advertises only S256.
    $response->assertJsonPath('code_challenge_methods_supported', ['S256']);
    $response->assertJsonPath('scopes_supported', ['mcp:use']);
});

test('behind a TLS-terminating proxy every URL is absolute https', function () {
    /** @var TestCase $this */
    // Behind Render's proxy, the app receives plain HTTP with X-Forwarded-Proto:
    // https. This checks we still advertise https URLs, which depends on
    // trustProxies() in bootstrap/app.php.
    $headers = ['X-Forwarded-Proto' => 'https'];

    $protectedResource = $this->getJson('http://davidharting.com/.well-known/oauth-protected-resource/mcp/admin', $headers);

    $protectedResource->assertOk();
    $protectedResource->assertJsonPath('resource', 'https://davidharting.com/mcp/admin');
    $protectedResource->assertJsonPath('authorization_servers.0', 'https://davidharting.com');

    $authorizationServer = $this->getJson('http://davidharting.com/.well-known/oauth-authorization-server', $headers);

    $authorizationServer->assertOk();
    $authorizationServer->assertJsonPath('issuer', 'https://davidharting.com');
    $authorizationServer->assertJsonPath('authorization_endpoint', 'https://davidharting.com/oauth/authorize');
    $authorizationServer->assertJsonPath('token_endpoint', 'https://davidharting.com/oauth/token');
    $authorizationServer->assertJsonPath('registration_endpoint', 'https://davidharting.com/oauth/register');
});
