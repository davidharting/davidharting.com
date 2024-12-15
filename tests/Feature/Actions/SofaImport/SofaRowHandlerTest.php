<?php

use App\Actions\SofaImport\Category;
use App\Actions\SofaImport\ListGroup;
use App\Actions\SofaImport\SofaList;
use App\Actions\SofaImport\SofaRow;
use App\Actions\SofaImport\SofaRowHandler;
use App\Enum\MediaEventTypeName;
use App\Models\Media;

function createRow(
    string $title,
    Category $category,
    SofaList $listName,
    ListGroup $group,
    DateTimeImmutable $dateAdded,
    DateTimeImmutable $dateEdited,
    ?string $notes
): SofaRow {
    $row = new SofaRow;

    $row->title = $title;
    $row->category = $category;
    $row->listName = $listName;
    $row->group = $group;
    $row->dateAdded = $dateAdded;
    $row->dateEdited = $dateEdited;
    $row->notes = $notes;

    return $row;
}

test('Logbook movie', function () {
    $row = createRow(
        'The Matrix',
        Category::Movie,
        SofaList::Logbook,
        ListGroup::Main,
        new DateTimeImmutable('2024-12-15 11:34:35'),
        new DateTimeImmutable('2024-12-15 11:34:35'),
        null
    );
    $handler = new SofaRowHandler($row);

    $report = $handler->handle();
    expect($report)->toBe([
        'creators' => 0,
        'media' => 1,
        'events' => 1,
    ]);

    $matrix = Media::where('title', 'The Matrix')->first();

    expect($matrix)->not->toBeNull();
    expect($matrix->events->count())->toBe(1);
    expect($matrix->events->first()->mediaEventType->name)->toBe(MediaEventTypeName::FINISHED);
    expect($matrix->events->first()->occurred_at->toDateString())->toBe('2024-12-15');

});

test('Abandoned book', function () {
    /** @var TestCase $this */
    $row = createRow(
        'Dr. Doolittle',
        Category::Book,
        SofaList::DidNotFinish,
        ListGroup::Tracking,
        new DateTimeImmutable('2021-07-06 11:34:35'),
        new DateTimeImmutable('2024-12-15 11:34:35'),
        null
    );
    $handler = new SofaRowHandler($row);

    $report = $handler->handle();
    expect($report)->toBe([
        'creators' => 0,
        'media' => 1,
        'events' => 1,
    ]);

    $doolittle = Media::where('title', 'Dr. Doolittle')->first();

    expect($doolittle)->not->toBeNull();
    expect($doolittle->events->count())->toBe(1);
    expect($doolittle->events->first()->mediaEventType->name)->toBe(MediaEventTypeName::ABANDONED);
    expect($doolittle->events->first()->occurred_at->toDateString())->toBe('2021-07-06');
});

test('Started albumn with note', function () {
    /** @var TestCase $this */
    $row = createRow(
        'Hot Fuss',
        Category::Album,
        SofaList::InProgress,
        ListGroup::Tracking,
        new DateTimeImmutable('2025-01-12 11:34:35'),
        new DateTimeImmutable('2025-02-15 11:34:35'),
        'Recommended by XYZ podcast'
    );

    $handler = new SofaRowHandler($row);
    $report = $handler->handle();
    expect($report)->toBe([
        'creators' => 0,
        'media' => 1,
        'events' => 1,
    ]);

    $hotFuss = Media::where('title', 'Hot Fuss')->first();
    expect($hotFuss)->not->toBeNull();
    expect($hotFuss->note)->toBe('Recommended by XYZ podcast');

    expect($hotFuss->events->count())->toBe(1);
    $event = $hotFuss->events->first();
    expect($event->mediaEventType->name)->toBe(MediaEventTypeName::STARTED);
    expect($event->occurred_at->toDateString())->toBe('2025-01-12');
});

test('Video games work', function () {
    $row = createRow(
        'Jusant',
        Category::VideoGame,
        SofaList::Logbook,
        ListGroup::Main,
        new DateTimeImmutable('2023-03-13 11:34:35'),
        new DateTimeImmutable('2023-04-12 11:34:35'),
        null
    );

    $handler = new SofaRowHandler($row);
    $report = $handler->handle();
    expect($report)->toBe([
        'creators' => 0,
        'media' => 1,
        'events' => 1,
    ]);

    $jusant = Media::where('title', 'Jusant')->first();
    expect($jusant)->not->toBeNull();

    expect($jusant->events->count())->toBe(1);
    $event = $jusant->events->first();
    expect($event->mediaEventType->name)->toBe(MediaEventTypeName::FINISHED);
    expect($event->occurred_at->toDateString())->toBe('2023-03-13');
});

test('idempotency', function () {
    $row = createRow(
        'The Matrix',
        Category::Movie,
        SofaList::Logbook,
        ListGroup::Main,
        new DateTimeImmutable('2024-12-15 11:34:35'),
        new DateTimeImmutable('2024-12-15 11:34:35'),
        null
    );

    $handler = new SofaRowHandler($row);
    $report = $handler->handle();
    expect($report)->toBe([
        'creators' => 0,
        'media' => 1,
        'events' => 1,
    ]);

    $matrix = Media::where('title', 'The Matrix')->first();
    expect($matrix)->not->toBeNull();
    expect($matrix->events->count())->toBe(1);

    // Re-import the same row
    $handler = new SofaRowHandler($row);
    $report = $handler->handle();
    expect($report)->toBe([
        'creators' => 0,
        'media' => 0,
        'events' => 0,
    ]);

    $matrix = Media::where('title', 'The Matrix')->first();
    expect($matrix)->not->toBeNull();
    expect($matrix->events->count())->toBe(1);
});
