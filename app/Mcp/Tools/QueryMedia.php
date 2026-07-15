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
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $status = isset($validated['status'])
            ? MediaTrackingStatus::from($validated['status'])
            : null;

        $query = new SearchMediaQuery(
            title: $validated['title'] ?? null,
            mediaType: isset($validated['media_type']) ? MediaTypeName::from($validated['media_type']) : null,
            creator: $validated['creator'] ?? null,
            status: $status,
            year: $validated['year'] ?? null,
            startedYear: $validated['started_year'] ?? null,
            finishedYear: $validated['finished_year'] ?? null,
            sort: isset($validated['sort'])
                ? MediaSort::from($validated['sort'])
                : $this->defaultSort($status),
        );

        $paginator = $query->paginate(
            perPage: $validated['limit'] ?? 25,
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
     * When the caller does not choose a sort, pick the one they most likely
     * mean: recently finished for finished items, recently started for
     * started items, and newest library entries otherwise.
     */
    private function defaultSort(?MediaTrackingStatus $status): MediaSort
    {
        return match ($status) {
            MediaTrackingStatus::Finished => MediaSort::RecentlyFinished,
            MediaTrackingStatus::Started => MediaSort::RecentlyStarted,
            default => MediaSort::RecentlyAdded,
        };
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('Match against the title (case-insensitive, partial match).'),
            'creator' => $schema->string()
                ->description('Match against the creator — author, director, artist, studio, etc. (case-insensitive, partial match).'),
            'media_type' => $schema->string()
                ->enum(array_column(MediaTypeName::cases(), 'value'))
                ->description('Only return items of this media type.'),
            'status' => $schema->string()
                ->enum(array_column(MediaTrackingStatus::cases(), 'value'))
                ->description('Only return items with this current tracking status. backlog means not yet started.'),
            'year' => $schema->integer()
                ->description('The release year of the work itself (e.g. the year a book was published). Distinct from started_year and finished_year.'),
            'started_year' => $schema->integer()
                ->description('The calendar year David started the item. Distinct from year, the release year of the work.'),
            'finished_year' => $schema->integer()
                ->description('The calendar year David finished the item. Distinct from year, the release year of the work.'),
            'sort' => $schema->string()
                ->enum(array_column(MediaSort::cases(), 'value'))
                ->description('Sort order. Defaults to recently_finished when status=finished, recently_started when status=started, and recently_added otherwise.'),
            'page' => $schema->integer()
                ->min(1)
                ->description('The page of results to return. Defaults to 1.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(100)
                ->description('How many results to return per page. Defaults to 25, maximum 100.'),
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
