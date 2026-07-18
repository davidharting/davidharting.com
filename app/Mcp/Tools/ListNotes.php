<?php

namespace App\Mcp\Tools;

use App\Models\Note;
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
    List the published notes (blog posts) on davidharting.com, most recently
    published first. Returns each note's slug, title, lead (subtitle), publication
    date, and canonical URL. The full markdown content is not included; pass a
    note's slug to the get-note tool to read it. Results are paginated — use the
    page and per_page arguments to walk through them.
    TEXT)]
class ListNotes extends Tool
{
    private const DEFAULT_PER_PAGE = 250;

    private const MAX_PER_PAGE = 250;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $paginator = Note::query()
            ->where('visible', true)
            ->orderByDesc('published_at')
            ->paginate(
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
            ])->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
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
                ]))
                ->description('The published notes on this page, most recently published first.'),
            'total' => $schema->integer()->description('The total number of published notes across all pages.'),
            'page' => $schema->integer()->description('The current page number.'),
            'per_page' => $schema->integer()->description('The number of notes per page.'),
            'has_more_pages' => $schema->boolean()->description('Whether more pages of notes are available.'),
        ];
    }
}
