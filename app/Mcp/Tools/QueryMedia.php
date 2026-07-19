<?php

namespace App\Mcp\Tools;

use App\Enum\MediaSort;
use App\Enum\MediaTrackingStatus;
use App\Enum\MediaTypeName;
use App\Models\MediaTrackingSummary;
use App\Queries\Media\SearchMediaQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
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
    Query David Harting's media tracking library: the albums, books, movies, TV
    shows, and video games he tracks, with their current status (backlog,
    started, finished, or abandoned) and the dates each status was reached. All
    filters are optional and combine with AND; with no arguments the whole
    library is returned, paginated. Examples: everything finished in 2025
    (status=finished, finished_year=2025); books in the backlog
    (media_type=book, status=backlog); anything by a given creator
    (creator=FromSoftware); what is being read right now (media_type=book,
    status=started). Note the difference between year (the work's release
    year) and started_year / finished_year (when David started or finished
    it).
    TEXT)]
class QueryMedia extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string'],
            'creator' => ['sometimes', 'string'],
            'media_type' => ['sometimes', 'string', Rule::enum(MediaTypeName::class)],
            'status' => ['sometimes', 'string', Rule::enum(MediaTrackingStatus::class)],
            'year' => ['sometimes', 'integer'],
            'started_year' => ['sometimes', 'integer'],
            'finished_year' => ['sometimes', 'integer'],
            'sort' => ['sometimes', 'string', Rule::enum(MediaSort::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.SearchMediaQuery::MAX_LIMIT],
        ]);

        $query = SearchMediaQuery::fromArray($validated);

        $paginator = $query->paginate(
            perPage: $validated['limit'] ?? SearchMediaQuery::DEFAULT_LIMIT,
            page: $validated['page'] ?? 1,
        );

        return Response::structured([
            'results' => collect($paginator->items())->map(fn (MediaTrackingSummary $item): array => [
                'media_id' => $item->media_id,
                'title' => $item->title,
                'year' => $item->year,
                'media_type' => $item->media_type,
                'creator' => $item->creator,
                'current_status' => $item->current_status,
                'started_at' => $item->started_at?->toIso8601String(),
                'finished_at' => $item->finished_at?->toIso8601String(),
                'abandoned_at' => $item->abandoned_at?->toIso8601String(),
            ])->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
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
        return SearchMediaQuery::inputSchema($schema);
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, JsonSchema>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'results' => $schema->array()
                ->items($schema->object([
                    'media_id' => $schema->integer()->description('The internal id of the media item.'),
                    'title' => $schema->string()->description('The title of the work.'),
                    'year' => $schema->integer()->nullable()->description('The release year of the work.'),
                    'media_type' => $schema->string()->description('One of: album, book, movie, tv show, video game.'),
                    'creator' => $schema->string()->nullable()->description('The creator of the work.'),
                    'current_status' => $schema->string()->description('One of: backlog, started, finished, abandoned.'),
                    'started_at' => $schema->string()->nullable()->description('When David first started the item (ISO 8601), if ever.'),
                    'finished_at' => $schema->string()->nullable()->description('When David most recently finished the item (ISO 8601), if ever.'),
                    'abandoned_at' => $schema->string()->nullable()->description('When David most recently abandoned the item (ISO 8601), if ever.'),
                ]))
                ->description('The matching media items.'),
            'total' => $schema->integer()->description('The total number of matching items across all pages.'),
            'page' => $schema->integer()->description('The current page number.'),
            'limit' => $schema->integer()->description('The number of results per page.'),
            'has_more_pages' => $schema->boolean()->description('Whether more pages of results are available.'),
        ];
    }
}
