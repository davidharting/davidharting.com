<?php

use App\Models\Note;
use Tests\TestCase;

test('guests can list tools over the streamable HTTP transport', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

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

test('guests can initialize a session and see the server identity', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/mcp', [
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
    $response->assertJsonPath('result.serverInfo.name', 'davidharting.com');
});

test('guests can call a tool over the streamable HTTP transport', function () {
    /** @var TestCase $this */
    Note::factory()->create(['title' => 'A public note', 'visible' => true]);

    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'list-notes',
            'arguments' => [],
        ],
    ]);

    $response->assertOk();
    $response->assertJsonMissingPath('error');
    $response->assertJsonPath('result.isError', false);
    $response->assertJsonPath('result.structuredContent.total', 1);
});

test('non-POST requests are rejected with a 405', function () {
    /** @var TestCase $this */
    $this->get('/mcp')->assertStatus(405);
    $this->delete('/mcp')->assertStatus(405);
});
