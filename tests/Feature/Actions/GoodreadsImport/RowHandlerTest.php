<?php

use App\Actions\GoodreadsImport\Row;
use App\Actions\GoodreadsImport\RowHandler;
use App\Actions\GoodreadsImport\Shelf;
use App\Enum\MediaEventTypeName;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use Tests\TestCase;

function createRow(
    string $title,
    Shelf $shelf,
    DateTimeImmutable $dateAdded,
    ?string $author = null,
    ?int $publicationYear = null,
    ?DateTimeImmutable $dateRead = null

): Row {
    $row = new Row;

    $row->title = $title;
    $row->author = $author;
    $row->shelf = $shelf;
    $row->publicationYear = $publicationYear;
    $row->dateAdded = $dateAdded;
    $row->dateRead = $dateRead;

    return $row;

}

test('skips if not read', function () {
    /** @var TestCase $this */
    $row = createRow(title: 'The Hobbit', shelf: Shelf::Backlog, dateAdded: new DateTimeImmutable('2021-01-01'));

    $handler = new RowHandler($row);
    $report = $handler->handle();
    expect($report)->toBe([
        'media' => 0,
        'creator' => 0,
        'events' => 0,
    ]);
});

test('book, no author', function () {
    /** @var TestCase $this */
    $row = createRow(
        title: 'The Hobbit',
        shelf: Shelf::Read,
        dateAdded: new DateTimeImmutable('2021-01-01'),
        dateRead: new DateTimeImmutable('2021-07-21')
    );

    $handler = new RowHandler($row);
    $report = $handler->handle();
    expect($report)->toBe([
        'media' => 1,
        'creator' => 0,
        'events' => 1,
    ]);

    $hobbit = Media::where('title', 'The Hobbit')->firstOrFail();

    expect($hobbit->created_at->toDateString())->toBe('2021-01-01');

    expect(MediaEvent::count())->toBe(1);
    expect(Creator::count())->toBe(0);

    $finishedEvent = $hobbit->events->first();
    expect($finishedEvent->mediaEventType->name)->toBe(MediaEventTypeName::FINISHED);
    expect($finishedEvent->occurred_at->toDateString())->toBe('2021-07-21');

});

test('book, author, and date read', function () {
    /** @var TestCase $this */
    $row = createRow(
        title: 'The Hobbit',
        shelf: Shelf::Read,
        author: 'J.R.R. Tolkien',
        dateAdded: new DateTimeImmutable('2018-10-17'),
        publicationYear: 1937,
        dateRead: new DateTimeImmutable('2019-7-12')
    );

    $handler = new RowHandler($row);
    $report = $handler->handle();
    expect($report)->toBe([
        'media' => 1,
        'creator' => 1,
        'events' => 1,
    ]);

    $hobbit = Media::where('title', 'The Hobbit')->firstOrFail();

    expect($hobbit->year)->toBe(1937);
    expect($hobbit->created_at->toDateString())->toBe('2018-10-17');
    expect($hobbit->creator->name)->toBe('J.R.R. Tolkien');
    expect($hobbit->title)->toBe('The Hobbit');

    $events = $hobbit->events;
    expect($events->count())->toBe(1);

    $readEvent = $events->first();

    expect($readEvent->mediaEventType->name)->toBe(MediaEventTypeName::FINISHED);

    expect(MediaEvent::count())->toBe(1);
    expect(Creator::count())->toBe(1);
    expect(Media::count())->toBe(1);
});

test('idempotency', function () {
    /** @var TestCase $this */
    $row = createRow(
        title: 'The Hobbit',
        shelf: Shelf::Read,
        author: 'J.R.R. Tolkien',
        dateAdded: new DateTimeImmutable('2018-10-17'),
        publicationYear: 1937,
        dateRead: new DateTimeImmutable('2019-7-12')
    );

    $handler = new RowHandler($row);
    $report1 = $handler->handle();
    expect($report1)->toBe([
        'media' => 1,
        'creator' => 1,
        'events' => 1,
    ]);

    $report2 = $handler->handle();
    expect($report2)->toBe([
        'media' => 0,
        'creator' => 0,
        'events' => 0,
    ]);

    expect(MediaEvent::count())->toBe(1);
    expect(Creator::count())->toBe(1);
    expect(Media::count())->toBe(1);
});
