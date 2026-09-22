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
        get-note to read one in full as markdown. Unpublished drafts are
        reachable here: get-note returns one whenever its slug is asked for,
        and list-notes includes them when you pass include_drafts. Read the
        status before treating a note as published — list-notes puts it in
        each result's status field, and get-note on its Status line. Either
        way it is "published" or "draft". search-notes never matches drafts.

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
