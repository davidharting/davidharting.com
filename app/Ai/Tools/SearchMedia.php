<?php

namespace App\Ai\Tools;

use App\Enum\MediaSort;
use App\Enum\MediaTrackingStatus;
use App\Enum\MediaTypeName;
use App\Queries\Media\SearchMediaQuery;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchMedia implements Tool
{
    private const DEFAULT_LIMIT = 25;

    private const MAX_LIMIT = 100;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return <<<'TEXT'
            Search and browse media items in the library. All filters are optional
            and combine with AND: title and creator (case-insensitive partial
            match), media_type (album, book, movie, tv show, video game), status
            (backlog, started, finished, abandoned), year (the work's release
            year), started_year and finished_year (the calendar year David started
            or finished the item). Results can be sorted and are paginated.
            Returns matching records including the title, year, media type,
            creator, current tracking status, and the dates each status was
            reached. Use it to check whether an item is already in the library, or
            to answer questions about the library ("what did David finish in
            2024?" -> finished_year=2024; "what is on the backlog?" ->
            status=backlog).
            TEXT;
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $title = ((string) $request->string('title')) ?: null;
        $creator = ((string) $request->string('creator')) ?: null;

        $mediaType = $this->enumFromRequest($request, 'media_type', MediaTypeName::class, $error);
        if ($error !== null) {
            return $error;
        }

        $status = $this->enumFromRequest($request, 'status', MediaTrackingStatus::class, $error);
        if ($error !== null) {
            return $error;
        }

        $sort = $this->enumFromRequest($request, 'sort', MediaSort::class, $error);
        if ($error !== null) {
            return $error;
        }

        $query = new SearchMediaQuery(
            title: $title,
            mediaType: $mediaType,
            creator: $creator,
            status: $status,
            year: $request->integer('year') ?: null,
            startedYear: $request->integer('started_year') ?: null,
            finishedYear: $request->integer('finished_year') ?: null,
            sort: $sort ?? $this->defaultSort($status),
        );

        $paginator = $query->paginate(
            perPage: min($request->integer('limit') ?: self::DEFAULT_LIMIT, self::MAX_LIMIT),
            page: max($request->integer('page') ?: 1, 1),
        );

        return json_encode([
            'found' => $paginator->total() > 0,
            'results' => collect($paginator->items())->toArray(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ], JSON_THROW_ON_ERROR);
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
     * Parse an optional backed-enum argument, setting $error to a JSON error
     * payload when the provided value is not a valid case.
     *
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     *
     * @param-out string|null $error
     *
     * @return TEnum|null
     */
    private function enumFromRequest(Request $request, string $key, string $enum, ?string &$error): ?BackedEnum
    {
        $error = null;
        $raw = ((string) $request->string($key)) ?: null;

        if ($raw === null) {
            return null;
        }

        $value = $enum::tryFrom(strtolower($raw));

        if ($value === null) {
            $valid = implode(', ', array_column($enum::cases(), 'value'));
            $error = json_encode(
                ['error' => "Invalid {$key} \"{$raw}\". Must be one of: {$valid}."],
                JSON_THROW_ON_ERROR,
            );
        }

        return $value;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('The title of the media item to search for (case-insensitive, partial match).'),
            'creator' => $schema->string()
                ->description('The creator (author, director, artist, etc.) to search for (case-insensitive, partial match).'),
            'media_type' => $schema->string()
                ->enum(array_column(MediaTypeName::cases(), 'value'))
                ->description('Optional media type filter.'),
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
                ->max(self::MAX_LIMIT)
                ->description(sprintf('How many results to return per page. Defaults to %d, maximum %d.', self::DEFAULT_LIMIT, self::MAX_LIMIT)),
        ];
    }
}
