<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Public, unauthenticated, read-only MCP server.
 *
 * The name is the contract: every tool registered here must only ever return
 * information that is already visible to a logged-out visitor on the website.
 * Authenticated capabilities belong on a separate server, never here (see
 * docs/projects/mcp-server.md).
 */
#[Name('davidharting.com')]
#[Version('1.0.0')]
#[Instructions('This server exposes the public content of davidharting.com, the personal website of David Harting: published notes (blog posts) and his media tracking library. All data available here is public — the same information a logged-out visitor sees on the website.')]
class PublicServer extends Server
{
    protected array $tools = [
        //
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
