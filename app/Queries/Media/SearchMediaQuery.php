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
    /**
     * The columns every caller may have. The view also has `note`, `full_text`
     * and `history` columns, which are admin-only; reaching them means setting
     * one of the flags below.
     */
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

    /**
     * This query performs no authorization. The last four parameters reach
     * admin-only data and the caller is responsible for authz checks.
     *
     * @param  string|null  $remarkOrComment  Matches the item's remark or any
     *                                        comment on its events. A *filter* on
     *                                        admin-only data is as disclosing as
     *                                        returning it: answers narrow by what
     *                                        the text says, so a caller who may
     *                                        not read a remark may not search it.
     * @param  bool  $includeRemark  Whether to return `media.note`, David's
     *                               private remark on the item.
     * @param  bool  $includeHistory  Whether to return the event timeline as
     *                                structured data. Off by default: it is an
     *                                array per row, and most questions are about
     *                                status and dates.
     * @param  bool  $includeFullText  Whether to return the remark and every event
     *                                 comment as one markdown string — the same
     *                                 writing as the history, in a shape meant for
     *                                 reading rather than processing.
     */
    public function __construct(
        public ?string $title = null,
        public ?MediaTypeName $mediaType = null,
        public ?string $creator = null,
        public ?MediaTrackingStatus $status = null,
        public ?int $year = null,
        public ?int $startedYear = null,
        public ?int $finishedYear = null,
        public ?MediaSort $sort = null,
        public ?string $remarkOrComment = null,
        public bool $includeRemark = false,
        public bool $includeHistory = false,
        public bool $includeFullText = false,
    ) {}

    /**
     * @return Collection<int, MediaTrackingSummary>
     */
    public function execute(): Collection
    {
        return $this->builder()->get($this->columns());
    }

    /**
     * @return LengthAwarePaginator<int, MediaTrackingSummary>
     */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        return $this->builder()->paginate(
            perPage: $perPage,
            columns: $this->columns(),
            page: $page,
        );
    }

    /**
     * @return string[]
     */
    private function columns(): array
    {
        return [
            ...self::COLUMNS,
            ...($this->includeRemark ? ['note'] : []),
            ...($this->includeHistory ? ['history'] : []),
            ...($this->includeFullText ? ['full_text'] : []),
        ];
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

        if ($this->remarkOrComment !== null) {
            $query->whereLike('full_text', '%'.LikePattern::escape($this->remarkOrComment).'%');
        }

        $this->applySort($query);

        return $query;
    }

    /**
     * @param  Builder<MediaTrackingSummary>  $query
     */
    private function applySort(Builder $query): void
    {
        match ($this->sort ?? MediaSort::RecentlyAdded) {
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
}
