<?php

use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * oauth_clients.id is a Postgres uuid column. Before Passport 13.10, a
 * non-UUID client_id reached the query and Postgres raised "invalid input
 * syntax for type uuid", which escaped as a 500 from every public OAuth
 * endpoint. Passport now refuses the malformed id before querying. These
 * pin that every endpoint answers it the same way it answers a well-formed
 * but unknown id: 401 invalid_client.
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

test('refuses a malformed client_id on the authorize page', function () {
    /** @var TestCase $this */
    $response = $this->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => 'nope',
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
    ]));

    $response->assertUnauthorized();
    $response->assertJsonPath('error', 'invalid_client');
});

test('refuses a well-formed but unknown client_id the same way', function () {
    /** @var TestCase $this */
    $response = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => (string) Str::orderedUuid(),
        'client_secret' => 'whatever',
    ]);

    $response->assertUnauthorized();
    $response->assertJsonPath('error', 'invalid_client');
});
