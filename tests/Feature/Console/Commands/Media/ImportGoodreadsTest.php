<?php

use App\Models\Creator;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;

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

    expect(Media::count())->toBe(0);
    expect(Creator::count())->toBe(0);
});

test('does a dry run by default', function () {
    /** @var TestCase $this */
    $export = app_path('Actions/GoodreadsImport/data/goodreads-export-20241129.csv');

    $this->artisan('media:import', ['file' => $export])
        ->assertSuccessful()
        ->expectsOutputToContain('Dry run completed');

    expect(Media::count())->toBe(0);
    expect(Creator::count())->toBe(0);
});
