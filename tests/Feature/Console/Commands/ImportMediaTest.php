<?php

use App\Models\Creator;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

test('fails on missing file argument', function () {
    /** @var TestCase $this */
    $this->expectExceptionMessage('Not enough arguments (missing: "file").');
    $this->artisan('media:import');
});

test('fails on invalid file header', function () {
    /** @var TestCase $this */
    Storage::fake('local');
    Storage::append('invalid.csv', 'year,title,creator');

    $this->artisan('media:import', ['file' => Storage::path('invalid.csv')])
        ->assertFailed()
        ->expectsOutputToContain('Invalid CSV file');

    $this->assertEmpty(Media::all());
    $this->assertEmpty(Creator::all());

});

test('does a dry run by default', function () {
    /** @var TestCase $this */
    Storage::fake('local');
    Storage::append('valid.csv', 'title,year,creator,note');
    Storage::append('valid.csv', 'The Great Gatsby,1925,F. Scott Fitzgerald,');
    Storage::append('valid.csv', 'To Kill a Mockingbird,1960,Harper Lee,This is a private note');
    Storage::append('valid.csv', '1984,1949,George Orwell,');
    Storage::append('valid.csv', 'The Beautiful and the Damned,,F. Scott Fitzgerald,');

    $this->artisan('media:import', ['file' => Storage::path('valid.csv')])
        ->expectsOutputToContain('dry run')
        ->expectsTable(
            headers: ['Type', 'Found', 'Imported'],
            rows: [
                ['Creators', 1, 3],
                ['Media', 0, 4],
            ]
        )
        ->assertExitCode(0);

    $this->assertEmpty(Media::all());
    $this->assertEmpty(Creator::all());
});

test('import', function () {
    /** @var TestCase $this */
    Storage::fake('local');
    Storage::append('valid.csv', 'title,year,creator,note');
    Storage::append('valid.csv', 'The Great Gatsby,1925,F. Scott Fitzgerald,');
    Storage::append('valid.csv', 'To Kill a Mockingbird,1960,Harper Lee,This is a private note');
    Storage::append('valid.csv', '1984,1949,George Orwell,');
    Storage::append('valid.csv', 'The Beautiful and the Damned,,F. Scott Fitzgerald,');

    $this->artisan('media:import', ['file' => Storage::path('valid.csv'), '--force' => true])
        ->expectsOutputToContain('Starting import')
        ->expectsTable(
            headers: ['Type', 'Found', 'Imported'],
            rows: [
                ['Creators', 1, 3],
                ['Media', 0, 4],
            ]
        )
        ->assertExitCode(0);

    $this->assertEquals(Media::count(), 4);
    $this->assertEquals(Creator::count(), 3);
});
