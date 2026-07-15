<?php

namespace App\Queries\Media;

use App\Enum\MediaSort;
use App\Enum\MediaTrackingStatus;
use App\Enum\MediaTypeName;
use App\Models\MediaTrackingSummary;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SearchMediaQuery
{
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
        if ($this->sort === null) {
            return;
        }

        match ($this->sort) {
            MediaSort::RecentlyFinished => $query->orderByRaw('finished_at desc nulls last'),
            MediaSort::RecentlyStarted => $query->orderByRaw('started_at desc nulls last'),
            MediaSort::RecentlyAdded => $query->orderByDesc('media_id'),
            MediaSort::Title => $query->orderBy('title'),
            MediaSort::Year => $query->orderByRaw('year desc nulls last'),
        };

        // Deterministic tiebreaker so paginated pages never overlap.
        $query->orderBy('media_id');
    }
}
