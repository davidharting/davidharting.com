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
    tool to read the whole note. Results are paginated — use the page and
    per_page arguments to walk through them.
    TEXT)]
class SearchNotes extends Tool
{
    /**
     * How many characters of context to include around the first match (on
     * each side of it) when building a snippet.
     */
    private const SNIPPET_CHARS_AROUND_MATCH = 120;

    private const MIN_QUERY_LENGTH = 4;

    private const DEFAULT_PER_PAGE = 250;

    private const MAX_PER_PAGE = 250;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:'.self::MIN_QUERY_LENGTH],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $query = $validated['query'];

        $paginator = (new SearchNotesQuery($query))->paginate(
            perPage: $validated['per_page'] ?? self::DEFAULT_PER_PAGE,
            page: $validated['page'] ?? 1,
        );

        return Response::structured([
            'notes' => collect($paginator->items())->map(fn (Note $note): array => [
                'slug' => $note->slug,
                'title' => $note->title,
                'lead' => $note->lead,
                'published_at' => $note->published_at?->toIso8601String(),
                'url' => route('notes.show', $note->slug),
                'snippet' => $this->snippet($note, $query),
            ])->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more_pages' => $paginator->hasMorePages(),
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
                ->min(self::MIN_QUERY_LENGTH)
                ->description(sprintf(
                    'The text to search for, at least %d characters long. Matched case-insensitively against note titles, leads, and markdown content.',
                    self::MIN_QUERY_LENGTH,
                )),
            'page' => $schema->integer()
                ->min(1)
                ->description('The page of results to return. Defaults to 1.'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(self::MAX_PER_PAGE)
                ->description(sprintf(
                    'How many notes to return per page. Defaults to %d, maximum %d.',
                    self::DEFAULT_PER_PAGE,
                    self::MAX_PER_PAGE,
                )),
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
                ->description('The matching published notes on this page, most recently published first.'),
            'total' => $schema->integer()->description('The total number of matching notes across all pages.'),
            'page' => $schema->integer()->description('The current page number.'),
            'per_page' => $schema->integer()->description('The number of notes per page.'),
            'has_more_pages' => $schema->boolean()->description('Whether more pages of matching notes are available.'),
        ];
    }
}
