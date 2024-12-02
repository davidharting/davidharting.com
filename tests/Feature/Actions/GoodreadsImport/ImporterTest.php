<?php

use App\Actions\GoodreadsImport\Importer;

test('import', function () {
    $export = app_path('Actions/GoodreadsImport/data/goodreads-export-20241129.csv');

    $importer = new Importer($export);
    $report = $importer->import();

    expect($report)->toBe([
        'media' => 188,
        'creator' => 130,
        'events' => 188,
    ]);
});
