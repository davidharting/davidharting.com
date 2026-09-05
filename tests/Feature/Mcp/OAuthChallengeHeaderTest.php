<?php

use App\Mcp\Servers\PublicServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Tests\TestCase;

/*
 * Covers App\Http\Middleware\AddMcpOAuthChallengeHeader, a workaround for
 * laravel/mcp#278. Delete this file together with that class once a released
 * laravel/mcp contains laravel/mcp#322 — see issue #194.
 *
 * These register a throwaway MCP server rather than using /mcp/admin, because
 * that server does not exist yet (#187) and the middleware's contract is about
 * any Mcp::web() route returning a 401, not one particular server.
 *
 * The 401 comes from closure middleware rather than a real `auth:api` guard so
 * these stay meaningful before Passport is installed (#185). What is under test
 * is the decision to decorate, not how the 401 was produced.
 */

beforeEach(function () {
    // auth:sanctum stands in for the auth:api guard /mcp/admin will use once
    // Passport is installed (#185). Any Illuminate\Auth\Middleware\Authenticate
    // reproduces the bug, because it is the $middlewarePriority sorting that puts
    // the 401 out of the package middleware's reach.
    Mcp::web('/mcp/test-protected', PublicServer::class)->middleware('auth:sanctum');
    Mcp::web('/mcp/test-open', PublicServer::class);

    // Not MCP servers: one under the /mcp prefix, one outside it.
    Route::get('/mcp/not-a-server', fn () => response('', 401));
    Route::get('/elsewhere/protected', fn () => response('', 401));
    Route::get('/elsewhere/open', fn () => response('fine', 200));
});

test('a 401 from an MCP server carries the OAuth challenge', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/mcp/test-protected', []);

    $response->assertStatus(401);
    expect($response->headers->get('WWW-Authenticate'))->toContain('Bearer realm="mcp"');
});

test('a successful MCP response is untouched', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/mcp/test-open', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertOk();
    expect($response->headers->get('WWW-Authenticate'))->toBeNull();
});

test('a 401 from a non-MCP route under /mcp is left alone', function () {
    /** @var TestCase $this */
    // Detection asks the registrar which routes are MCP servers rather than
    // matching the URL, so sharing the /mcp prefix is not enough to be decorated.
    $response = $this->get('/mcp/not-a-server');

    $response->assertStatus(401);
    expect($response->headers->get('WWW-Authenticate'))->toBeNull();
});

test('a 401 from an unrelated route is left alone', function () {
    /** @var TestCase $this */
    // The package middleware only checks the status code, so registering it
    // globally as upstream does would stamp an MCP OAuth challenge onto every
    // 401 in the app. Scoping is the reason this wrapper exists.
    $response = $this->get('/elsewhere/protected');

    $response->assertStatus(401);
    expect($response->headers->get('WWW-Authenticate'))->toBeNull();
});

test('a successful unrelated response is untouched', function () {
    /** @var TestCase $this */
    $response = $this->get('/elsewhere/open');

    $response->assertOk();
    expect($response->headers->get('WWW-Authenticate'))->toBeNull();
});

test('the public MCP server is unaffected', function () {
    /** @var TestCase $this */
    // /mcp is anonymous and must stay that way — it should never return a 401,
    // so it should never acquire a challenge header.
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertOk();
    expect($response->headers->get('WWW-Authenticate'))->toBeNull();
});
