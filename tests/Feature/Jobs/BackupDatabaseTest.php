<?php

use App\Jobs\BackupDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

test('happy path', function () {
    /** @var TestCase $this */
    Storage::fake('local-private');

    Process::fake([
        'sqlite3 *' => Process::result(output: ''),
        'gzip *' => Process::result(output: 'compressed backup'),
    ]);

    BackupDatabase::dispatchSync('backup');

    Storage::assertExists('backup');
});

test('failure', function () {
    /** @var TestCase $this */
    Storage::fake('local-private');

    Process::fake([
        'sqlite3 *' => Process::result(exitCode: 1, errorOutput: 'sqlite3: error'),
        'gzip *' => Process::result(output: 'compressed backup'),
    ]);

    $this->assertThrows(function () {
        BackupDatabase::dispatchSync('backup');
    }, RuntimeException::class);

    Process::assertDidntRun('gzip *');

    $this->assertFalse(Storage::exists('backup'));
});
