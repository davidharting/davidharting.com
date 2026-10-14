<?php

use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * oauth_clients.id is a Postgres uuid column. Passport's public OAuth
 * endpoints pass client_id straight into a lookup against it, so a
 * non-UUID value used to make Postgres raise "invalid input syntax for
 * type uuid", which escaped as an uncaught QueryException (a 500). These
 * pin PassportClientRepository::find() rejecting the malformed id before
 * it reaches the query, so every endpoint refuses it the same way it
 * already refuses a well-formed but unmatched UUID: a 4xx invalid_client.
 */

test('refuses a malformed client_id on the client_credentials grant', function () {
    /** @var TestCase $this */
    $response = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => 'nope',
        'client_secret' => 'whatever',
    ]);

    $response->assertUnauthorized();
    $response->assertJsonPath('error', 'invalid_client');
});

test('refuses a malformed client_id on the refresh_token grant', function () {
    /** @var TestCase $this */
    $response = $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => 'nope',
        'refresh_token' => 'whatever',
    ]);

    $response->assertUnauthorized();
    $response->assertJsonPath('error', 'invalid_client');
});

test('refuses a malformed client_id on the authorization_code grant', function () {
    /** @var TestCase $this */
    $response = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => 'nope',
        'code' => 'whatever',
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
    ]);

    $response->assertUnauthorized();
    $response->assertJsonPath('error', 'invalid_client');
});

test('refuses a malformed client_id on GET /oauth/authorize', function () {
    /** @var TestCase $this */
    $response = $this->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => 'nope',
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
    ]));

    $response->assertUnauthorized();
    $response->assertJsonPath('error', 'invalid_client');
});

test('a well-formed but unmatched client_id gets the identical response', function () {
    /** @var TestCase $this */
    $response = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => (string) Str::orderedUuid(),
        'client_secret' => 'whatever',
    ]);

    $response->assertUnauthorized();
    $response->assertJsonPath('error', 'invalid_client');
});
