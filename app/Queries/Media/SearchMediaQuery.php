<?php

namespace App\Queries\Media;

use App\Enum\MediaTypeName;
use App\Models\MediaTrackingSummary;
use App\Support\LikePattern;
use Illuminate\Support\Collection;

class SearchMediaQuery
{
    public function __construct(
        public ?string $title = null,
        public ?MediaTypeName $mediaType = null,
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
            $query->where('media_type', $this->mediaType->value);
        }

        return $query->get([
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
        ]);
    }
}
