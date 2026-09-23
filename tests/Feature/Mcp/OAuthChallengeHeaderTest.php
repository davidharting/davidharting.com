<?php

use App\Mcp\Servers\PublicServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Tests\TestCase;

/*
 * Pins how laravel/mcp's AddWwwAuthenticateHeader decides which 401s get an MCP
 * OAuth challenge. The package registers it as *global* middleware, because
 * Laravel sorts `auth:api` ahead of route middleware and a route-level
 * decorator never sees the 401 (laravel/mcp#278).
 *
 * Global middleware sees every response in the app, so what matters is that it
 * stays scoped to MCP routes: the web UI's 401s must never point a browser at
 * MCP resource metadata. v1.0.0 scopes on the route's own middleware list; this
 * app shipped its own scoped wrapper until then (#194).
 *
 * These register throwaway servers rather than using /mcp/admin because the
 * contract is about any Mcp::web() route returning a 401, not one server.
 */

beforeEach(function () {
    // auth:api is the guard /mcp/admin will actually use (#187). It is
    // Illuminate\Auth\Middleware\Authenticate, which the middleware priority list
    // sorts ahead of the package's route middleware — the ordering that puts the
    // 401 out of the decorator's reach in the first place.
    Mcp::web('/mcp/test-protected', PublicServer::class)->middleware('auth:api');
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
    // Detection is by the route's middleware, not its URL, so sharing the /mcp
    // prefix is not enough to be decorated.
    $response = $this->get('/mcp/not-a-server');

    $response->assertStatus(401);
    expect($response->headers->get('WWW-Authenticate'))->toBeNull();
});

test('a 401 from an unrelated route is left alone', function () {
    /** @var TestCase $this */
    // The middleware is global, so only its own scoping keeps an MCP OAuth
    // challenge off every other 401 in the app.
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
