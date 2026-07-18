<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use App\Queries\SearchNotesQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
#[Description(<<<'TEXT'
    Search the published notes (blog posts) on davidharting.com. The query is
    matched case-insensitively against each note's title, lead (subtitle), and
    full markdown content. Returns matching notes (most recently published
    first) with a short snippet of the surrounding content where the query
    matched, so you can judge relevance. Pass a result's slug to the get-note
    tool to read the whole note.
    TEXT)]
class SearchNotes extends Tool
{
    /**
     * How many characters of context to include around the first match (on
     * each side of it) when building a snippet.
     */
    private const SNIPPET_CHARS_AROUND_MATCH = 120;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['required', 'string'],
        ]);

        $query = $validated['query'];

        $notes = (new SearchNotesQuery($query))->execute();

        return Response::structured([
            'notes' => $notes->map(fn (Note $note): array => [
                'slug' => $note->slug,
                'title' => $note->title,
                'lead' => $note->lead,
                'published_at' => $note->published_at?->toIso8601String(),
                'url' => route('notes.show', $note->slug),
                'snippet' => $this->snippet($note, $query),
            ])->all(),
            'total' => $notes->count(),
        ]);
    }

    /**
     * Extract the content surrounding the first match in the note's markdown,
     * so agents can judge relevance without fetching every note.
     */
    private function snippet(Note $note, string $query): ?string
    {
        $content = $note->markdown_content;

        if (! $content) {
            return null;
        }

        $position = mb_stripos($content, $query);

        if ($position === false) {
            // The match was in the title or lead, which are already in the response.
            return null;
        }

        $start = max(0, $position - self::SNIPPET_CHARS_AROUND_MATCH);
        $snippet = mb_substr($content, $start, mb_strlen($query) + self::SNIPPET_CHARS_AROUND_MATCH * 2);

        return ($start > 0 ? '…' : '')
            .trim($snippet)
            .($start + mb_strlen($snippet) < mb_strlen($content) ? '…' : '');
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->required()
                ->description('The text to search for. Matched case-insensitively against note titles, leads, and markdown content.'),
        ];
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, JsonSchema>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'notes' => $schema->array()
                ->items($schema->object([
                    'slug' => $schema->string()->description('The unique slug identifying the note.'),
                    'title' => $schema->string()->nullable()->description('The note title. Short notes may have no title.'),
                    'lead' => $schema->string()->nullable()->description('The lead (subtitle) of the note.'),
                    'published_at' => $schema->string()->nullable()->description('The publication timestamp in ISO 8601 format.'),
                    'url' => $schema->string()->description('The canonical URL of the note on davidharting.com.'),
                    'snippet' => $schema->string()->nullable()->description('The content surrounding the first match in the note body. Null when the match was only in the title or lead.'),
                ]))
                ->description('The matching published notes, most recently published first.'),
            'total' => $schema->integer()->description('The total number of matching notes.'),
        ];
    }
}
