<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Process\Pipe;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BackupDatabase implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $path) {}

    public function handle(): void
    {

        $result = Process::pipe(function (Pipe $pipe) {
            $backupCommand = Str::of('pg_dump')->append(' -U ')
                ->append(config('database.connections.pgsql.username'))
                ->append(' --format tar ')
                ->append(config('database.connections.pgsql.database'));

            $pipe->as('pg_dump')->command($backupCommand);
            $pipe->as('gzip')->command('gzip');
        });

        if (($result->failed())) {

            // Log the error output
            Log::error('Database backup failed', ['stdEr' => $result->errorOutput()]);
            // Raise an appropriate exception
            throw new RuntimeException('Database backup failed: ' . $result->errorOutput());
        }

        Storage::disk('local')->put($this->path, $result->output());

        Log::info('Database backup completed successfully', [
            'file' => $this->path,
        ]);
    }
}
