<?php

namespace App\Queries\Media;

use App\Models\MediaTrackingSummary;
use App\Support\LikePattern;
use Illuminate\Support\Collection;

class SearchMediaQuery
{
    public function __construct(
        public ?string $title = null,
        // This is not AI agent code here... relaly we should expect a MediaType enum here, not string
        public ?string $mediaType = null,
        public ?string $creator = null,
    ) {}

    /**
     * @return Collection<int, MediaTrackingSummary>
     */
    public function execute(): Collection
    {
        $query = MediaTrackingSummary::query();

        if ($this->title !== null) {
            $query->whereLike('title', '%'.LikePattern::escape($this->title).'%');
        }

        if ($this->creator !== null) {
            $query->whereLike('creator', '%'.LikePattern::escape($this->creator).'%');
        }

        if ($this->mediaType !== null) {
            $query->whereRaw('LOWER(media_type) = LOWER(?)', [$this->mediaType]);
        }

        return $query->get([
            'media_id',
            'title',
            'year',
            'media_type',
            'creator',
            'current_status',
            'started_at',
            'finished_at',
            'abandoned_at',
        ]);
    }
}
