<?php

use App\Models\Note;
use Tests\TestCase;

test('guests can list tools over the streamable HTTP transport', function () {
    /** @var TestCase $this */
    $response = postMcp('/mcp', 'tools/list');

    $response->assertOk();
    $response->assertJsonPath('jsonrpc', '2.0');
    $response->assertJsonPath('id', 1);
    $response->assertJsonMissingPath('error');

    $toolNames = collect($response->json('result.tools'))->pluck('name')->all();
    expect($toolNames)->toBe([
        'list-notes',
        'search-notes',
        'get-note',
        'query-media',
    ]);
});

test('guests can discover the server identity', function () {
    /** @var TestCase $this */
    $response = postMcp('/mcp', 'server/discover');

    $response->assertOk();
    $response->assertJsonMissingPath('error');

    expect($response->json('result.supportedVersions'))->toBe(['2026-07-28']);
    expect($response->json('result.instructions'))->toContain('davidharting.com');
});

test('the legacy initialize handshake is gone', function () {
    /** @var TestCase $this */
    // MCP 2026-07-28 removed the initialize/notifications/initialized handshake in favour
    // of `discover`. Pinned deliberately: this is the change that drops support for every
    // client speaking an older protocol revision, so it should fail loudly if it ever
    // silently comes back.
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'legacy', 'version' => '1.0.0'],
        ],
    ]);

    $response->assertNotFound();
    $response->assertJsonPath('error.data.supported', ['2026-07-28']);
});

test('guests can call a tool over the streamable HTTP transport', function () {
    /** @var TestCase $this */
    Note::factory()->create(['title' => 'A public note', 'visible' => true]);

    $response = postMcp('/mcp', 'tools/call', [
        'name' => 'list-notes',
        'arguments' => [],
    ], id: 2);

    $response->assertOk();
    $response->assertJsonMissingPath('error');
    $response->assertJsonPath('result.isError', false);
    $response->assertJsonPath('result.structuredContent.total', 1);
});

test('requests that omit the protocol headers are rejected', function () {
    /** @var TestCase $this */
    // Header-based routing is mandatory since 2026-07-28; a bare JSON-RPC body is no
    // longer a valid request even when its contents are perfectly well formed.
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertStatus(400);
    $response->assertJsonPath('error.code', -32020);
});

test('non-POST requests are rejected with a 405', function () {
    /** @var TestCase $this */
    $this->get('/mcp')->assertStatus(405);
    $this->delete('/mcp')->assertStatus(405);
});
