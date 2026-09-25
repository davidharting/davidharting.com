<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Admin\CreateMedia;
use App\Mcp\Tools\Admin\CreateMediaEvent;
use App\Mcp\Tools\Admin\EditMedia;
use App\Mcp\Tools\Admin\EditMediaEvent;
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
    /**
     * Kept under 2 KB: Claude Code truncates server instructions past that, and
     * claude.ai drops them entirely (#218). Rules a tool call must follow belong
     * in that tool's description, the only channel every client delivers.
     */
    protected string $instructions = <<<'MARKDOWN'
        This server exposes the content of davidharting.com, the personal
        website of David Harting, to David himself.

        **Notes** are David's blog posts, published or draft. Use list-notes,
        search-notes and get-note to read them; check each note's status
        before treating it as published.

        **The media library** tracks the albums, books, movies, TV shows and
        video games David engages with: each item's status (backlog, started,
        finished or abandoned), when it got there, and what he wrote about it.
        Use query-media to find items, create-media and create-media-event to
        log them, and edit-media and edit-media-event to correct mistakes.
        Each tool's description gives the rules for using it.

        Drafts, remarks on media items and comments on their events are
        private writing, not on the public website. Never quote or attribute
        them as something David has said in public.
        MARKDOWN;

    protected array $tools = [
        AdminListNotes::class,
        AdminSearchNotes::class,
        AdminGetNote::class,
        AdminQueryMedia::class,
        CreateMedia::class,
        CreateMediaEvent::class,
        EditMedia::class,
        EditMediaEvent::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
