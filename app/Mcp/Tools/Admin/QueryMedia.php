<?php

namespace App\Mcp\Tools\Admin;

use App\Enum\MediaSort;
use App\Enum\MediaTrackingStatus;
use App\Enum\MediaTypeName;
use App\Models\Media;
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

/**
 * The admin counterpart of App\Mcp\Tools\QueryMedia, registered only on
 * AdminServer.
 *
 * Do not collapse the two into one class with a flag. A tool's name,
 * description and schemas come from class attributes and never see the request,
 * so a single class would have to describe both surfaces at once — and here the
 * two surfaces differ in their arguments, not just their output.
 */
#[IsReadOnly]
#[IsIdempotent]
#[Description(<<<'TEXT'
    Query David Harting's media tracking library: the albums, books, movies, TV
    shows, and video games he tracks, with their current status (backlog,
    started, finished, or abandoned), the dates each status was reached, and his
    private remarks. All filters are optional and combine with AND; with no
    arguments the whole library is returned, paginated.

    Examples: everything finished in 2025 (status=finished, finished_year=2025);
    books in the backlog (media_type=book, status=backlog); what is being read
    right now (media_type=book, status=started); anything he wrote "disappointing"
    about (full_text_query=disappointing).

    Note the difference between year (the work's release year) and started_year /
    finished_year (when David started or finished it).

    Every response includes the same base fields. Ask for more with
    additional_fields: full_text for everything David has written about an item
    as one markdown string, or history for the event timeline as structured data,
    one entry per event. They hold the same writing in two shapes — full_text to
    read it, history when you need the individual events. Both are left out
    unless asked for.

    The remark, full_text, history and the full_text_query filter all reach
    writing that is not on the public website. Treat it as David's notes to
    himself, not as published opinion.
    TEXT)]
class QueryMedia extends Tool
{
    /**
     * Fields a caller can ask for on top of the base set. Each is expensive
     * enough per row to be worth leaving out by default.
     */
    private const ADDITIONAL_FIELDS = ['full_text', 'history'];

    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found": both resolve through the same filtered collection.
     *
     * handle() asks the narrower question of whether the caller may see the
     * private writing this tool returns. Neither check substitutes for the other.
     */
    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->can('administrate') ?? false;
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        // MediaPolicy::seeNote is the same gate media/index.blade.php puts on
        // the remark and event comments, so this tool and the website agree on
        // who may read them. All other fields are public on the site.
        if ($request->user()?->cannot('seeNote', Media::class) ?? true) {
            return Response::error('You are not authorized to read David\'s remarks.');
        }

        $validated = $this->validatedArguments($request);

        $status = isset($validated['status'])
            ? MediaTrackingStatus::from($validated['status'])
            : null;

        $additionalFields = $validated['additional_fields'] ?? [];
        $includeHistory = in_array('history', $additionalFields, true);
        $includeFullText = in_array('full_text', $additionalFields, true);

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
            fullTextQuery: $validated['full_text_query'] ?? null,
            includeRemark: true,
            includeHistory: $includeHistory,
            includeFullText: $includeFullText,
        );

        $paginator = $query->paginate(
            perPage: $validated['limit'] ?? 25,
            page: $validated['page'] ?? 1,
        );

        return Response::structured([
            'results' => collect($paginator->items())
                ->map(fn (MediaTrackingSummary $item): array => $this->toResult($item, $includeHistory, $includeFullText))
                ->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ]);
    }

    /**
     * An invalid argument throws a ValidationException, which laravel/mcp turns
     * into an error result naming the failing field — the tool never sees it.
     *
     * @return array{
     *     title?: string,
     *     creator?: string,
     *     media_type?: string,
     *     status?: string,
     *     year?: int,
     *     started_year?: int,
     *     finished_year?: int,
     *     full_text_query?: string,
     *     additional_fields?: list<'full_text'|'history'>,
     *     sort?: string,
     *     page?: int,
     *     limit?: int,
     * }
     */
    private function validatedArguments(Request $request): array
    {
        return $request->validate([
            'title' => ['sometimes', 'string'],
            'creator' => ['sometimes', 'string'],
            'media_type' => ['sometimes', 'string', Rule::enum(MediaTypeName::class)],
            'status' => ['sometimes', 'string', Rule::enum(MediaTrackingStatus::class)],
            'year' => ['sometimes', 'integer'],
            'started_year' => ['sometimes', 'integer'],
            'finished_year' => ['sometimes', 'integer'],
            'full_text_query' => ['sometimes', 'string'],
            'additional_fields' => ['sometimes', 'array'],
            'additional_fields.*' => ['string', Rule::in(self::ADDITIONAL_FIELDS)],
            'sort' => ['sometimes', 'string', Rule::enum(MediaSort::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toResult(MediaTrackingSummary $item, bool $includeHistory, bool $includeFullText): array
    {
        $result = [
            'media_id' => $item->media_id,
            'title' => $item->title,
            'year' => $item->year,
            'media_type' => $item->media_type,
            'creator' => $item->creator,
            'creator_id' => $item->creator_id,
            'current_status' => $item->current_status,
            'started_at' => $item->started_at?->toIso8601String(),
            'finished_at' => $item->finished_at?->toIso8601String(),
            'abandoned_at' => $item->abandoned_at?->toIso8601String(),
            'remark' => $item->note,
        ];

        // Omitted rather than null when not asked for: a null would read as "no
        // writing" or "no events", which is a different claim from "not fetched".
        if ($includeFullText) {
            $result['full_text'] = $item->full_text;
        }

        if ($includeHistory) {
            $result['history'] = $item->history;
        }

        return $result;
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
            'full_text_query' => $schema->string()
                ->description('Search the same writing the full_text field returns — David\'s remark on the item and every comment on its events (case-insensitive, partial match, not word-based). Use this to find items by what he said about them rather than by their title or status.'),
            'additional_fields' => $schema->array()
                ->items($schema->string()->enum(self::ADDITIONAL_FIELDS))
                ->description('Extra fields to return on each result. full_text is everything David has written about the item as one markdown string, best for reading. history is the event timeline as structured data, one entry per event. Both are omitted unless listed here; the current status and the started/finished/abandoned dates are returned either way.'),
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
                    'creator_id' => $schema->integer()->nullable()->description('The internal id of the creator, for passing to create-media. Null when the item has no creator.'),
                    'current_status' => $schema->string()->description('One of: backlog, started, finished, abandoned.'),
                    'started_at' => $schema->string()->nullable()->description('When David first started the item (ISO 8601), if ever.'),
                    'finished_at' => $schema->string()->nullable()->description('When David most recently finished the item (ISO 8601), if ever.'),
                    'abandoned_at' => $schema->string()->nullable()->description('When David most recently abandoned the item (ISO 8601), if ever.'),
                    'remark' => $schema->string()->nullable()->description('David\'s private standing remark on the item. Not on the public website. Null when he has not written one.'),
                    'full_text' => $schema->string()->nullable()->description('The remark followed by every event comment, as one markdown string with each comment dated. Private. Null when he has written nothing about the item. Present only when full_text is in additional_fields.'),
                    'history' => $schema->array()
                        ->items($schema->object([
                            'event_id' => $schema->integer()->description('The id of the event.'),
                            'type' => $schema->string()->description('One of: backlog, started, finished, abandoned, comment.'),
                            'occurred_at' => $schema->string()->description('When the event happened (ISO 8601).'),
                            'comment' => $schema->string()->nullable()->description('What David wrote at that moment. Private. Null when the event carries no comment.'),
                        ]))
                        ->description('Every tracking event for the item, oldest first. Present only when history is in additional_fields.'),
                ]))
                ->description('The matching media items.'),
            'total' => $schema->integer()->description('The total number of matching items across all pages.'),
            'page' => $schema->integer()->description('The current page number.'),
            'limit' => $schema->integer()->description('The number of results per page.'),
            'has_more_pages' => $schema->boolean()->description('Whether more pages of results are available.'),
        ];
    }
}
