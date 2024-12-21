<?php

namespace App\Actions\SofaImport;

use App\Enum\MediaEventTypeName;
use App\Models\Creator;
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
        ], [
            'note' => $this->row->notes,
            'created_at' => $this->row->dateAdded,
            'updated_at' => $this->row->dateAdded,
        ]);

        $isNew = $this->media->wasRecentlyCreated;

        // created_at and updated_at were not fillable before I ran this the first time.
        // So update that so that media timestamps reflect when I added them to lists rather than current time
        if (! $isNew) {
            $this->media->update([
                'updated_at' => $this->row->dateAdded,
                'created_at' => $this->row->dateAdded,
            ]);
        }


        // TODO: Add a note if the item already exists (i.e., was imported from goodreads without a note)
        if (! $isNew && ! is_null($this->row->notes)) {
            $this->media->note = $this->row->notes;
            $this->media->save();
        }

        return $isNew ? 1 : 0;
    }

    private function handleCreator(): int
    {
        if (is_null($this->row->creator)) {
            return 0;
        }

        $creator = Creator::firstOrCreate([
            'name' => $this->row->creator,
        ]);

        $this->media->creator()->associate($creator);
        $this->media->save();

        return $creator->wasRecentlyCreated ? 1 : 0;
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
     * function the row and return an array of key-value pairs.
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
        $creators = $this->handleCreator();

        return [
            'creators' => $creators,
            'media' => $media,
            'events' => $events,
        ];
    }
}
