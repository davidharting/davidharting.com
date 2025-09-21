<?php

use App\Jobs\BackupDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

test('happy path', function () {
    /** @var TestCase $this */
    Storage::fake('local-private');

    Process::fake([
        'pg_dump *' => Process::result(output: 'some tar file'),
        'gzip' => Process::result(output: 'compressed tar file'),
    ]);

    BackupDatabase::dispatchSync('backup');

    Storage::assertExists('backup', "compressed tar file\n");
});

test('failure', function () {
    /** @var TestCase $this */
    Storage::fake('local-private');

    Process::fake([
        'pg_dump *' => Process::result(exitCode: 1, errorOutput: 'pg_dump: error'),
        'gzip' => Process::result(output: 'compressed tar file'),
    ]);

    $this->assertThrows(function () {
        BackupDatabase::dispatchSync('backup');
    }, RuntimeException::class);

    Process::assertDidntRun('gzip');

    $this->assertFalse(Storage::exists('backup'));
});
