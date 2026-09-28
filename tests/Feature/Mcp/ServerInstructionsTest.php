<?php

use App\Models\User;
use Tests\TestCase;

/*
 * Claude Code truncates a server's instructions past 2 KB, and claude.ai does
 * not deliver them at all (#218). Anything past the limit is text only some
 * clients ever see, so the limit is enforced on the bytes actually served.
 */

const MCP_INSTRUCTIONS_BYTE_LIMIT = 2048;

function initializeRequest(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'pest', 'version' => '1.0.0'],
        ],
    ];
}

test('the public server instructions fit within the limit', function () {
    /** @var TestCase $this */
    $instructions = $this->postJson('/mcp', initializeRequest())
        ->assertOk()
        ->json('result.instructions');

    expect($instructions)->toBeString()->not->toBeEmpty()
        ->and(strlen($instructions))->toBeLessThanOrEqual(MCP_INSTRUCTIONS_BYTE_LIMIT);
});

test('the admin server instructions fit within the limit', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $instructions = $this->withToken(accessTokenFor($admin, ['mcp:use']))
        ->postJson('/mcp/admin', initializeRequest())
        ->assertOk()
        ->json('result.instructions');

    expect($instructions)->toBeString()->not->toBeEmpty()
        ->and(strlen($instructions))->toBeLessThanOrEqual(MCP_INSTRUCTIONS_BYTE_LIMIT);
});
