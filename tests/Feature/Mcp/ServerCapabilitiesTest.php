<?php

use App\Models\User;
use Tests\Support\McpRequest;
use Tests\TestCase;

/*
 * laravel/mcp advertises tools, resources and prompts by default, whether or
 * not a server registers any. Neither server has prompts, and only the admin
 * server has a resource (the flashcard app), so each must say only what it
 * has: advertising more sends clients to ask for lists that are always empty
 * (#218 logged claude.ai doing exactly that on every connect).
 */

test('the public server advertises only tools', function () {
    /** @var TestCase $this */
    $capabilities = $this->postJson('/mcp', McpRequest::initialize())
        ->assertOk()
        ->json('result.capabilities');

    expect(array_keys($capabilities))->toBe(['tools']);
});

test('the admin server advertises tools, resources and the apps extension', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $capabilities = $this->withToken(accessTokenFor($admin, ['mcp:use']))
        ->postJson('/mcp/admin', McpRequest::initialize())
        ->assertOk()
        ->json('result.capabilities');

    // Resources serve the flashcard app's HTML; the extension tells MCP
    // Apps hosts they can render it.
    expect(array_keys($capabilities))->toBe(['tools', 'resources', 'extensions'])
        ->and(array_keys($capabilities['extensions']))->toBe(['io.modelcontextprotocol/ui']);
});
