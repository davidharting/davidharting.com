<?php

namespace App\Queries\Media;

use App\Models\MediaTrackingSummary;
use Illuminate\Support\Collection;

class SearchMediaQuery
{
    public function __construct(
        public ?string $title = null,
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
            $query->whereRaw(
                'title ILIKE ? ESCAPE ?',
                ['%'.$this->escapeLikeWildcards($this->title).'%', '\\'],
            );
        }

        if ($this->creator !== null) {
            $query->whereRaw(
                'creator ILIKE ? ESCAPE ?',
                ['%'.$this->escapeLikeWildcards($this->creator).'%', '\\'],
            );
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

    /**
     * Escape LIKE/ILIKE wildcard characters so user input is matched literally.
     * Without this, '%' and '_' in the search term would be treated as wildcards.
     */
    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
