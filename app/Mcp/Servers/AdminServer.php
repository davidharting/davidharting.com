<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Admin\GetNote as AdminGetNote;
use App\Mcp\Tools\Admin\ListNotes as AdminListNotes;
use App\Mcp\Tools\QueryMedia;
use App\Mcp\Tools\SearchNotes;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Admin-only MCP server at /mcp/admin.
 *
 * Tools must still check the same gates and policies the Blade templates use,
 * so a routing mistake becomes a policy denial rather than a data leak.
 */
#[Name('davidharting.com (admin)')]
#[Version('1.0.0')]
class AdminServer extends Server
{
    protected string $instructions = <<<'MARKDOWN'
        This server exposes the content of davidharting.com, the personal
        website of David Harting, to David himself. There are two kinds of
        content:

        **Notes** are David's blog posts. Use list-notes to browse them
        (newest first), search-notes to find notes matching a query, and
        get-note to read one in full as markdown. list-notes and get-note can
        both reach unpublished drafts, which search-notes cannot — pass
        include_drafts to either when the question is about work in progress,
        and read the status before treating a note as published.

        **The media library** tracks the albums, books, movies, TV shows, and
        video games David engages with. Each item has a current status —
        backlog (not started), started, finished, or abandoned — plus the dates
        those statuses were reached. Use query-media to filter by any
        combination of title, creator, media type, status, release year, and
        the year an item was started or finished.

        Of these, list-notes and get-note read more than the public website
        shows. search-notes and query-media still return the same public view
        as the unauthenticated server at /mcp, and no tool here can record what
        David is engaging with yet.
        MARKDOWN;

    protected array $tools = [
        AdminListNotes::class,
        SearchNotes::class,
        AdminGetNote::class,
        QueryMedia::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
