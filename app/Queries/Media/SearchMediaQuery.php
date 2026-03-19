<?php

namespace App\Queries\Media;

use App\Models\MediaTrackingSummary;
use Illuminate\Support\Collection;

class SearchMediaQuery
{
    public function __construct(
        public string $title,
        public ?string $mediaType = null,
    ) {}

    /**
     * @return Collection<int, MediaTrackingSummary>
     */
    public function execute(): Collection
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->title);
        $query = MediaTrackingSummary::whereRaw('LOWER(title) LIKE LOWER(?) ESCAPE \'\\\'', ['%'.$escaped.'%']);

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
