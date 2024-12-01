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

test('just the book', function () {
    /** @var TestCase $this */
    $row = createRow(title: 'The Hobbit', shelf: Shelf::Read, dateAdded: new DateTimeImmutable('2021-01-01'));

    $handler = new RowHandler($row);
    $handler->handle();

    $hobbit = Media::where('title', 'The Hobbit')->firstOrFail();

    expect($hobbit->created_at->toDateString())->toBe('2021-01-01');

    expect(MediaEvent::count())->toBe(0);
    expect(Creator::count())->toBe(0);
});

test('book and author', function () {
    /** @var TestCase $this */
    $row = createRow(
        title: 'The Hobbit',
        shelf: Shelf::Abandoned,
        author: 'J.R.R. Tolkien',
        dateAdded: new DateTimeImmutable('2018-10-17'),
        publicationYear: 1937
    );

    $handler = new RowHandler($row);
    $handler->handle();

    $hobbit = Media::where('title', 'The Hobbit')->firstOrFail();
    expect($hobbit->year)->toBe(1937);
    expect($hobbit->created_at->toDateString())->toBe('2018-10-17');
    expect($hobbit->creator->name)->toBe('J.R.R. Tolkien');
    expect($hobbit->title)->toBe('The Hobbit');

    expect(MediaEvent::count())->toBe(0);
    expect(Creator::count())->toBe(1);
    expect(Media::count())->toBe(1);
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
    $handler->handle();

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
    $handler->handle();
    $handler->handle();

    expect(MediaEvent::count())->toBe(1);
    expect(Creator::count())->toBe(1);
    expect(Media::count())->toBe(1);
});
