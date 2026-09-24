<?php

use App\Models\Media;
use App\Models\Note;
use App\Models\User;
use Tests\TestCase;

/*
 * These tests send JSON-RPC over HTTP instead of using AdminServer::tool().
 * That helper creates the server in-process with a fake transport and calls the
 * method handler directly, so routing and the auth:api, CheckToken, and
 * can:administrate middleware never run.
 */

function toolsListRequest(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ];
}

test('an anonymous request is rejected', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/mcp/admin', toolsListRequest());

    $response->assertUnauthorized();
});

test('an anonymous request is answered with the OAuth challenge that starts discovery', function () {
    /** @var TestCase $this */
    // The header comes from laravel/mcp's global AddWwwAuthenticateHeader
    // middleware (see OAuthChallengeHeaderTest). It tells Claude where our OAuth
    // metadata lives when a connection is set up or has to re-authorize. Without it, Claude falls back
    // to probing /.well-known/oauth-protected-resource/mcp/admin (covered by
    // OAuthDiscoveryTest), so connecting still works only while that path does.
    $response = $this->postJson('/mcp/admin', toolsListRequest());

    expect($response->headers->get('WWW-Authenticate'))->toContain('Bearer realm="mcp"');
});

test('a token without the mcp:use scope is refused', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withToken(accessTokenFor($admin))
        ->postJson('/mcp/admin', toolsListRequest());

    $response->assertForbidden();
});

test('a non-admin with a correctly scoped token is forbidden', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->withToken(accessTokenFor($user, ['mcp:use']))
        ->postJson('/mcp/admin', toolsListRequest());

    $response->assertForbidden();
});

test('an admin can list the tools', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
        ->postJson('/mcp/admin', toolsListRequest());

    $response->assertOk();
    $response->assertJsonMissingPath('error');

    $toolNames = collect($response->json('result.tools'))->pluck('name')->all();
    expect($toolNames)->toBe([
        'list-notes',
        'search-notes',
        'get-note',
        'query-media',
        'create-media',
    ]);
});

test('an admin can initialize a session and see the admin server identity', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
        ->postJson('/mcp/admin', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'pest', 'version' => '1.0.0'],
            ],
        ]);

    $response->assertOk();
    $response->assertJsonPath('result.serverInfo.name', 'davidharting.com (admin)');
});

test('an admin can call a tool', function () {
    /** @var TestCase $this */
    Note::factory()->create(['title' => 'A public note', 'visible' => true]);
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
        ->postJson('/mcp/admin', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list-notes',
                'arguments' => [],
            ],
        ]);

    $response->assertOk();
    $response->assertJsonPath('result.isError', false);
    $response->assertJsonPath('result.structuredContent.total', 1);
});

test('an admin is served the admin list-notes, not the public one', function () {
    /** @var TestCase $this */
    // Asserted over a real bearer token rather than through the in-process
    // AdminServer::tool() helper, which never runs auth:api. A test that stubs
    // the guard can pass while the deployed server admits nobody, or everybody.
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
        ->postJson('/mcp/admin', toolsListRequest());

    $response->assertOk();

    $listNotes = collect($response->json('result.tools'))->firstWhere('name', 'list-notes');

    expect(array_keys($listNotes['inputSchema']['properties']))->toBe(['include_drafts', 'page', 'per_page']);
});

test('an admin can read a draft through list-notes', function () {
    /** @var TestCase $this */
    Note::factory()->create(['title' => 'An unpublished draft', 'visible' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
        ->postJson('/mcp/admin', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list-notes',
                'arguments' => ['include_drafts' => true],
            ],
        ]);

    $response->assertOk();
    $response->assertJsonPath('result.isError', false);
    $response->assertJsonPath('result.structuredContent.total', 1);
    $response->assertJsonPath('result.structuredContent.notes.0.title', 'An unpublished draft');
    $response->assertJsonPath('result.structuredContent.notes.0.status', 'draft');
});

test('an admin can read a draft through get-note', function () {
    /** @var TestCase $this */
    // Asserted over a real bearer token rather than through the in-process
    // AdminServer::tool() helper, which never runs auth:api. A test that stubs
    // the guard can pass while the deployed server admits nobody, or everybody.
    $note = Note::factory()->create(['title' => 'An unpublished draft', 'visible' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
        ->postJson('/mcp/admin', [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get-note',
                'arguments' => ['slug' => $note->slug],
            ],
        ]);

    $response->assertOk();
    $response->assertJsonPath('result.isError', false);
    $response->assertSee('An unpublished draft');
    $response->assertSee('Status: draft');
});

test('an admin can read a remark through query-media', function () {
    /** @var TestCase $this */
    // Asserted over a real bearer token rather than through the in-process
    // AdminServer::tool() helper, which never runs auth:api. A test that stubs
    // the guard can pass while the deployed server admits nobody, or everybody.
    Media::factory()->book()->create(['title' => 'Dune', 'note' => 'Worth the hype']);
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
        ->postJson('/mcp/admin', [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'query-media',
                'arguments' => ['title' => 'Dune'],
            ],
        ]);

    $response->assertOk();
    $response->assertJsonPath('result.isError', false);
    $response->assertJsonPath('result.structuredContent.results.0.remark', 'Worth the hype');
});

test('an admin can search drafts through search-notes', function () {
    /** @var TestCase $this */
    // Asserted over a real bearer token rather than through the in-process
    // AdminServer::tool() helper, which never runs auth:api. A test that stubs
    // the guard can pass while the deployed server admits nobody, or everybody.
    Note::factory()->create(['title' => 'An unpublished xylophone', 'visible' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withToken(accessTokenFor($admin, ['mcp:use']))
        ->postJson('/mcp/admin', [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => [
                'name' => 'search-notes',
                'arguments' => ['query' => 'xylophone', 'include_drafts' => true],
            ],
        ]);

    $response->assertOk();
    $response->assertJsonPath('result.isError', false);
    $response->assertJsonPath('result.structuredContent.notes.0.title', 'An unpublished xylophone');
    $response->assertJsonPath('result.structuredContent.notes.0.status', 'draft');
});
