<?php

use App\Actions\SofaImport\Importer;
use Tests\TestCase;

test('runs', function () {
    /** @var TestCase $this */
    $importer = new Importer;

    $importer->import();
})->throwsNoExceptions();
