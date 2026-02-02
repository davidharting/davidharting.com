<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupDatabase implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $path) {}

    public function handle(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->backupSqlite();
        } else {
            $this->backupPostgres();
        }

        Log::info('Database backup completed successfully', [
            'file' => $this->path,
        ]);
    }

    private function backupSqlite(): void
    {
        $databasePath = config('database.connections.sqlite.database');

        // Use SQLite's backup command for a consistent backup
        $tempBackup = tempnam(sys_get_temp_dir(), 'sqlite_backup_');

        $result = Process::run(
            "sqlite3 {$databasePath} \".backup '{$tempBackup}'\""
        );

        if ($result->failed()) {
            Log::error('Database backup failed', ['stdErr' => $result->errorOutput()]);
            throw new RuntimeException('Database backup failed: '.$result->errorOutput());
        }

        // Compress and store
        $gzipResult = Process::run("gzip -c {$tempBackup}");

        if ($gzipResult->failed()) {
            unlink($tempBackup);
            Log::error('Database backup compression failed', ['stdErr' => $gzipResult->errorOutput()]);
            throw new RuntimeException('Database backup compression failed: '.$gzipResult->errorOutput());
        }

        Storage::disk()->put($this->path, $gzipResult->output());
        unlink($tempBackup);
    }

    private function backupPostgres(): void
    {
        $result = Process::pipe(function ($pipe) {
            $backupCommand = sprintf(
                'pg_dump -U %s --host %s --port %s --format tar %s',
                config('database.connections.pgsql.username'),
                config('database.connections.pgsql.host'),
                config('database.connections.pgsql.port'),
                config('database.connections.pgsql.database')
            );

            $pipe->as('pg_dump')->command($backupCommand)->env([
                'PGPASSWORD' => config('database.connections.pgsql.password'),
            ]);
            $pipe->as('gzip')->command('gzip');
        });

        if ($result->failed()) {
            Log::error('Database backup failed', ['stdErr' => $result->errorOutput()]);
            throw new RuntimeException('Database backup failed: '.$result->errorOutput());
        }

        Storage::disk()->put($this->path, $result->output());
    }
}
