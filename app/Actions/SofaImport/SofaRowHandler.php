<?php

namespace App\Actions\SofaImport;

use App\Enum\MediaEventTypeName;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\MediaEventType;

class SofaRowHandler
{
    private SofaRow $row;

    private Media $media;

    /**
     * Create a new class instance.
     */
    public function __construct(
        SofaRow $row
    ) {
        $this->row = $row;
    }

    private function handleMedia(): int
    {
        $mediaType = $this->row->category->getMediaType();
        $this->media = Media::firstOrCreate([
            'title' => $this->row->title,
            'media_type_id' => $mediaType->id,
        ], ['note' => $this->row->notes]);

        // TODO: Add Note

        return $this->media->wasRecentlyCreated ? 1 : 0;
    }

    private function handleEvents(): int
    {

        if ($this->row->listName == SofaList::Logbook) {
            $mediaEventType = MediaEventType::where('name', MediaEventTypeName::FINISHED)->first();

            $event = MediaEvent::firstOrCreate([
                'media_id' => $this->media->id,
                'media_event_type_id' => $mediaEventType->id,
            ], ['occurred_at' => $this->row->dateAdded]);

            return $event->wasRecentlyCreated ? 1 : 0;
        }

        if ($this->row->listName == SofaList::DidNotFinish) {
            $mediaEventType = MediaEventType::where('name', MediaEventTypeName::ABANDONED)->first();

            $event = MediaEvent::firstOrCreate([
                'media_id' => $this->media->id,
                'media_event_type_id' => $mediaEventType->id,
            ], ['occurred_at' => $this->row->dateAdded]);

            return $event->wasRecentlyCreated ? 1 : 0;
        }

        if ($this->row->listName == SofaList::InProgress) {
            $mediaEventType = MediaEventType::where('name', MediaEventTypeName::STARTED)->first();

            $event = MediaEvent::firstOrCreate([
                'media_id' => $this->media->id,
                'media_event_type_id' => $mediaEventType->id,
            ], ['occurred_at' => $this->row->dateAdded]);

            return $event->wasRecentlyCreated ? 1 : 0;
        }

        return 0;
    }

    /**
     * Handle the row and return an array of key-value pairs.
     *
     * @return array{
     *     creators: int,
     *     media: int,
     *     events: int
     * }
     */
    public function handle(): array
    {
        $media = $this->handleMedia();
        $events = $this->handleEvents();

        return [
            'creators' => 0,
            'media' => $media,
            'events' => $events,
        ];
    }
}
