<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetNote;
use App\Mcp\Tools\ListNotes;
use App\Mcp\Tools\QueryMedia;
use App\Mcp\Tools\SearchNotes;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Public, unauthenticated, read-only MCP server.
 *
 * The name is the contract: every tool registered here must only ever return
 * information that is already visible to a logged-out visitor on the website.
 * Authenticated capabilities belong on a separate server, never here (see
 * docs/projects/done/mcp-server.md).
 */
#[Name('davidharting.com')]
#[Version('1.0.0')]
class PublicServer extends Server
{
    protected string $instructions = <<<'MARKDOWN'
        This server exposes the public content of davidharting.com, the personal
        website of David Harting. Everything available here is public — the same
        information a logged-out visitor sees on the website. There are two kinds
        of content:

        **Notes** are David's blog posts. Use list-notes to browse them
        (newest first), search-notes to find notes matching a query, and
        get-note to read one in full as markdown.

        **The media library** tracks the albums, books, movies, TV shows, and
        video games David engages with. Each item has a current status —
        backlog (not started), started, finished, or abandoned — plus the dates
        those statuses were reached. Use query-media to filter by any
        combination of title, creator, media type, status, release year, and
        the year an item was started or finished.
        MARKDOWN;

    protected array $tools = [
        ListNotes::class,
        SearchNotes::class,
        GetNote::class,
        QueryMedia::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
