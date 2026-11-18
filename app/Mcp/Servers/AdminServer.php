<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\FlashcardApp;
use App\Mcp\Tools\Admin\AddWord;
use App\Mcp\Tools\Admin\CreateMedia;
use App\Mcp\Tools\Admin\CreateMediaEvent;
use App\Mcp\Tools\Admin\EditMedia;
use App\Mcp\Tools\Admin\EditMediaEvent;
use App\Mcp\Tools\Admin\EditWord;
use App\Mcp\Tools\Admin\GetNote as AdminGetNote;
use App\Mcp\Tools\Admin\GradeFlashcard;
use App\Mcp\Tools\Admin\ListNotes as AdminListNotes;
use App\Mcp\Tools\Admin\ListWords;
use App\Mcp\Tools\Admin\NextFlashcard;
use App\Mcp\Tools\Admin\QueryMedia as AdminQueryMedia;
use App\Mcp\Tools\Admin\RevealFlashcard;
use App\Mcp\Tools\Admin\SearchNotes as AdminSearchNotes;
use App\Mcp\Tools\Admin\ShowFlashcard;
use App\Mcp\Tools\Admin\UpdateCreator;
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
     * Tools, and resources for the flashcard app's HTML. The package default
     * also advertises prompts, which this server does not have, so clients
     * would ask for a list that is always empty.
     *
     * @var array<string, array<string, bool>>
     */
    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
        self::CAPABILITY_RESOURCES => [
            'listChanged' => false,
        ],
    ];

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
        log them, and edit-media, edit-media-event and update-creator to
        correct mistakes.

        **Words** are David's private vocabulary list: words he wants to
        remember, often ones he came across while reading, each with a short
        definition. Use list-words to browse them, and add-word and
        edit-word to record them. To quiz David on them with spaced
        repetition, start with next-flashcard, or show-flashcard for a card
        he flips and grades himself. Each tool's description gives
        the rules for using it.

        Drafts, remarks on media items and comments on their events are
        private writing, not on the public website. Never quote or attribute
        them as something David has said in public.
        MARKDOWN;

    /**
     * The package lists 15 per page by default, and this server now has
     * more tools than that. One page keeps every tool visible to a client
     * that doesn't follow nextCursor. 50 is also the package's maximum
     * ($maxPaginationLength), so past 50 tools paging returns.
     */
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        AdminListNotes::class,
        AdminSearchNotes::class,
        AdminGetNote::class,
        AdminQueryMedia::class,
        CreateMedia::class,
        CreateMediaEvent::class,
        EditMedia::class,
        EditMediaEvent::class,
        UpdateCreator::class,
        AddWord::class,
        EditWord::class,
        ListWords::class,
        NextFlashcard::class,
        RevealFlashcard::class,
        GradeFlashcard::class,
        ShowFlashcard::class,
    ];

    protected array $resources = [
        FlashcardApp::class,
    ];

    protected array $prompts = [
        //
    ];
}
