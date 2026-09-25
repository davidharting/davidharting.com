<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Admin\CreateMedia;
use App\Mcp\Tools\Admin\CreateMediaEvent;
use App\Mcp\Tools\Admin\GetNote as AdminGetNote;
use App\Mcp\Tools\Admin\ListNotes as AdminListNotes;
use App\Mcp\Tools\Admin\QueryMedia as AdminQueryMedia;
use App\Mcp\Tools\Admin\SearchNotes as AdminSearchNotes;
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
        and list-notes and search-notes include them when you pass
        include_drafts. Read the status before treating a note as published —
        list-notes and search-notes put it in each result's status field, and
        get-note on its Status line. Either way it is "published" or "draft".

        **The media library** tracks the albums, books, movies, TV shows, and
        video games David engages with. Each item has a current status —
        backlog (not started), started, finished, or abandoned — plus the dates
        those statuses were reached, and whatever David has written about it.
        Use query-media to filter by any combination of title, creator, media
        type, status, release year, and the year an item was started or
        finished. It also searches his remarks and comments on an item
        (full_text_query), and can return that writing — as one readable
        string (full_text) or as a structured event timeline (history) — via
        additional_fields.

        What David has written privately — a note still in draft, a remark on a
        media item, a comment on one of its events — is reachable through these
        tools and is not on the public website. It is private writing not published
        opinion, so never quote or attribute it as something he has said in
        public.

        **Adding to the library.** Use create-media to add an item, or to find
        it if it is already there; it never changes an existing item. Tell
        David whenever it matched an existing item instead of adding one, and
        name any supplied fields it reports as ignored. Use create-media-event
        to record that David started, finished or abandoned an item, or to add
        a dated comment to it. Every call logs a new event: when it reports
        other events of the same type, tell David, in case this one is a
        duplicate.
        MARKDOWN;

    protected array $tools = [
        AdminListNotes::class,
        AdminSearchNotes::class,
        AdminGetNote::class,
        AdminQueryMedia::class,
        CreateMedia::class,
        CreateMediaEvent::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
