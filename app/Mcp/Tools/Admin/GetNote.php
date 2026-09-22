<?php

namespace App\Mcp\Tools\Admin;

use App\Models\Note;
use App\Queries\GetNoteQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The admin counterpart of App\Mcp\Tools\GetNote, registered only on
 * AdminServer.
 *
 * Do not collapse the two into one class with a flag. A tool's name,
 * description and schemas come from class attributes and never see the request,
 * so a single class would have to describe both surfaces at once — and the
 * description is how the model learns drafts are reachable here at all.
 */
#[IsReadOnly]
#[IsIdempotent]
#[Description(<<<'TEXT'
    Read one of David's notes (blog posts) in full, as markdown. Identify the
    note by its slug, as returned by the list-notes or search-notes tools. The
    response includes the note's title, lead (subtitle), status, publication
    date, canonical URL, and complete markdown content.

    Published notes and unpublished drafts are both returned. Every response
    carries a Status line saying which it is — read it. A draft is work in
    progress, so never quote or summarise one as something David has
    published.
    TEXT)]
class GetNote extends Tool
{
    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found": both resolve through the same filtered collection.
     *
     * handle() asks the narrower question of whether the caller may read the
     * one note they named. Neither check substitutes for the other.
     */
    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->can('administrate') ?? false;
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'slug' => ['required', 'string'],
        ]);

        $note = (new GetNoteQuery($validated['slug'], includeDrafts: true))->execute();

        // NotePolicy::view is the same check routes/web.php puts on
        // notes.show, so this tool and the website agree on who may read a
        // given note. It denies with 404 for a draft, which is why a denial
        // and a missing slug answer alike here.
        if ($note === null || $request->user()?->cannot('view', $note)) {
            return Response::error('Note not found.');
        }

        return Response::text($this->toMarkdown($note));
    }

    private function toMarkdown(Note $note): string
    {
        $lines = [];

        if ($note->title) {
            $lines[] = "# {$note->title}";
        }

        if ($note->lead) {
            $lines[] = "*{$note->lead}*";
        }

        $lines[] = $note->visible
            ? 'Status: published'
            : 'Status: draft — not on davidharting.com, and the URL below 404s until it is published';

        $lines[] = 'Published: '.$note->published_at?->toDateString();
        $lines[] = 'URL: '.route('notes.show', $note->slug);

        if ($note->markdown_content) {
            $lines[] = '---';
            $lines[] = $note->markdown_content;
        }

        return implode("\n\n", $lines);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()
                ->required()
                ->description('The slug of the note to fetch, as returned by the list-notes or search-notes tools.'),
        ];
    }
}
