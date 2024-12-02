<?php

namespace App\Actions\GoodreadsImport;

use App\Enum\MediaEventTypeName;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\MediaEventType;
use App\Models\MediaType;
use Illuminate\Support\Str;

/**
 * Find or create data based on a single row of a Goodreads export CSV
 */
class RowHandler
{
    public function __construct(public readonly Row $row) {}

    /**
     * Handles the Goodreads import row.
     *
     * @return array{
     *     media: int,
     *     creator: int,
     *     events: int
     * }
     */
    public function handle(): array
    {
        $report = [
            'media' => 0,
            'creator' => 0,
            'events' => 0,
        ];

        $bookMediaType = MediaType::where('name', 'book')->first();
        $finishedEventType = MediaEventType::where('name', MediaEventTypeName::FINISHED)->first();
        $cleanTitle = Str::of($this->row->title)->trim()->replace('  ', ' ');

        $creator = null;
        if ($this->row->author !== null) {
            $creator = Creator::firstOrCreate([
                'name' => $this->row->author,
            ]);
            if ($creator->wasRecentlyCreated) {
                $report['creator'] = 1;
            }
        }

        // Find or create a book
        $book = Media::firstOrNew([
            'title' => $cleanTitle,
            'media_type_id' => $bookMediaType->id,
            'year' => $this->row->publicationYear,
        ]);
        if (! $book->exists) {
            $report['media'] = 1;
        }
        $book->year = $this->row->publicationYear;
        $book->created_at = $this->row->dateAdded;
        $book->updated_at = $this->row->dateAdded;
        if ($creator !== null) {
            $book->creator()->associate($creator);
        }

        $book->save();

        if ($this->row->dateRead) {
            $finishedEvent = MediaEvent::firstOrNew([
                'media_id' => $book->id,
                'media_event_type_id' => $finishedEventType->id,
            ]);
            if (! $finishedEvent->exists) {
                $report['events'] = 1;
            }
            $finishedEvent->occurred_at = $this->row->dateRead;

            $finishedEvent->save();
        }

        return $report;
    }
}
