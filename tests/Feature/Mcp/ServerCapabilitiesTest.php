<?php

use App\Models\User;
use Tests\Support\McpRequest;
use Tests\TestCase;

/*
 * laravel/mcp advertises tools, resources and prompts by default, whether or
 * not a server registers any. Neither server has resources or prompts, so both
 * must say so: advertising them sends clients to ask for lists that are always
 * empty (#218 logged claude.ai doing exactly that on every connect).
 */

test('the public server advertises only tools', function () {
    /** @var TestCase $this */
    $capabilities = $this->postJson('/mcp', McpRequest::initialize())
        ->assertOk()
        ->json('result.capabilities');

    expect(array_keys($capabilities))->toBe(['tools']);
});

test('the admin server advertises only tools', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    $capabilities = $this->withToken(accessTokenFor($admin, ['mcp:use']))
        ->postJson('/mcp/admin', McpRequest::initialize())
        ->assertOk()
        ->json('result.capabilities');

    expect(array_keys($capabilities))->toBe(['tools']);
});
