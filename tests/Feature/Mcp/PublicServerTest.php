<?php

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

test('non-POST requests are rejected with a 405', function () {
    /** @var TestCase $this */
    $this->get('/mcp')->assertStatus(405);
    $this->delete('/mcp')->assertStatus(405);
});
