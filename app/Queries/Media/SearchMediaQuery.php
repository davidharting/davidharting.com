<?php

namespace App\Queries\Media;

use App\Enum\MediaSort;
use App\Enum\MediaTrackingStatus;
use App\Enum\MediaTypeName;
use App\Models\MediaTrackingSummary;
use App\Support\LikePattern;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SearchMediaQuery
{
    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 100;

    private const COLUMNS = [
        'media_id',
        'creator_id',
        'title',
        'year',
        'media_type',
        'creator',
        'current_status',
        'started_at',
        'finished_at',
        'abandoned_at',
    ];

    public function __construct(
        public ?string $title = null,
        public ?MediaTypeName $mediaType = null,
        public ?string $creator = null,
        public ?MediaTrackingStatus $status = null,
        public ?int $year = null,
        public ?int $startedYear = null,
        public ?int $finishedYear = null,
        public ?MediaSort $sort = null,
    ) {}

    /**
     * Build a query from a tool-argument array keyed by the field names
     * described by inputSchema(). Enum values must already be validated
     * against their enum's cases.
     *
     * @param  array{title?: string|null, creator?: string|null, media_type?: string|null, status?: string|null, year?: int|null, started_year?: int|null, finished_year?: int|null, sort?: string|null}  $args
     */
    public static function fromArray(array $args): self
    {
        return new self(
            title: $args['title'] ?? null,
            mediaType: isset($args['media_type']) ? MediaTypeName::from($args['media_type']) : null,
            creator: $args['creator'] ?? null,
            status: isset($args['status']) ? MediaTrackingStatus::from($args['status']) : null,
            year: $args['year'] ?? null,
            startedYear: $args['started_year'] ?? null,
            finishedYear: $args['finished_year'] ?? null,
            sort: isset($args['sort']) ? MediaSort::from($args['sort']) : null,
        );
    }

    /**
     * The JSON schema fields describing this query's arguments, exposed to
     * LLMs by the QueryMedia tool.
     *
     * @return array<string, JsonSchema>
     */
    public static function inputSchema(JsonSchema $schema): array
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
                ->max(self::MAX_LIMIT)
                ->description(sprintf('How many results to return per page. Defaults to %d, maximum %d.', self::DEFAULT_LIMIT, self::MAX_LIMIT)),
        ];
    }

    /**
     * @return Collection<int, MediaTrackingSummary>
     */
    public function execute(): Collection
    {
        return $this->builder()->get(self::COLUMNS);
    }

    /**
     * @return LengthAwarePaginator<int, MediaTrackingSummary>
     */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        return $this->builder()->paginate(
            perPage: $perPage,
            columns: self::COLUMNS,
            page: $page,
        );
    }

    /**
     * @return Builder<MediaTrackingSummary>
     */
    private function builder(): Builder
    {
        $query = MediaTrackingSummary::query();

        if ($this->title !== null) {
            $query->whereLike('title', '%'.LikePattern::escape($this->title).'%');
        }

        if ($this->creator !== null) {
            $query->whereLike('creator', '%'.LikePattern::escape($this->creator).'%');
        }

        if ($this->mediaType !== null) {
            $query->where('media_type', $this->mediaType->value);
        }

        if ($this->status !== null) {
            $query->where('current_status', $this->status->value);
        }

        if ($this->year !== null) {
            $query->where('year', $this->year);
        }

        if ($this->startedYear !== null) {
            $query->whereYear('started_at', $this->startedYear);
        }

        if ($this->finishedYear !== null) {
            $query->whereYear('finished_at', $this->finishedYear);
        }

        $this->applySort($query);

        return $query;
    }

    /**
     * @param  Builder<MediaTrackingSummary>  $query
     */
    private function applySort(Builder $query): void
    {
        match ($this->sort ?? $this->defaultSort()) {
            MediaSort::RecentlyFinished => $query->orderByRaw('finished_at desc nulls last'),
            MediaSort::RecentlyStarted => $query->orderByRaw('started_at desc nulls last'),
            MediaSort::RecentlyAdded => $query->orderByDesc('media_id'),
            MediaSort::Title => $query->orderBy('title'),
            MediaSort::Creator => $query->orderBy('creator'),
            MediaSort::Year => $query->orderByRaw('year desc nulls last'),
        };

        // Deterministic tiebreaker so paginated pages never overlap.
        $query->orderBy('media_id');
    }

    /**
     * When the caller does not choose a sort, pick the one they most likely
     * mean: recently finished for finished items, recently started for
     * started items, and newest library entries otherwise.
     */
    private function defaultSort(): MediaSort
    {
        return match ($this->status) {
            MediaTrackingStatus::Finished => MediaSort::RecentlyFinished,
            MediaTrackingStatus::Started => MediaSort::RecentlyStarted,
            default => MediaSort::RecentlyAdded,
        };
    }
}
