<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/*
 * Covers App\Http\Middleware\AddMcpOAuthChallengeHeader, a workaround for
 * laravel/mcp#278. Delete this file together with that class once a released
 * laravel/mcp contains laravel/mcp#322 — see issue #194.
 *
 * These use throwaway routes rather than /mcp/admin because that server does not
 * exist yet (#187), and because the middleware's contract is about *any* 401 under
 * an MCP path, not about one particular server.
 */

beforeEach(function () {
    Route::get('/mcp/pretend-protected', fn () => response('', 401));
    Route::get('/mcp/pretend-ok', fn () => response('fine', 200));
    Route::get('/not-mcp/pretend-protected', fn () => response('', 401));
});

test('a 401 under an MCP path carries the OAuth challenge', function () {
    /** @var TestCase $this */
    $response = $this->get('/mcp/pretend-protected');

    $response->assertStatus(401);
    expect($response->headers->get('WWW-Authenticate'))->toContain('Bearer realm="mcp"');
});

test('a 401 outside MCP paths is left alone', function () {
    /** @var TestCase $this */
    // The package middleware only checks the status code, so registering it globally
    // as upstream does would stamp an MCP OAuth challenge onto every 401 in the app.
    // Scoping to MCP paths is the whole reason this wrapper exists rather than a
    // one-line Kernel entry.
    $response = $this->get('/not-mcp/pretend-protected');

    $response->assertStatus(401);
    expect($response->headers->get('WWW-Authenticate'))->toBeNull();
});

test('successful MCP responses are untouched', function () {
    /** @var TestCase $this */
    $response = $this->get('/mcp/pretend-ok');

    $response->assertOk();
    expect($response->headers->get('WWW-Authenticate'))->toBeNull();
});

test('the public MCP server is unaffected', function () {
    /** @var TestCase $this */
    // /mcp is anonymous and must stay that way — it should never return a 401, so it
    // should never acquire a challenge header.
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertOk();
    expect($response->headers->get('WWW-Authenticate'))->toBeNull();
});
