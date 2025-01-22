<?php

namespace App\Livewire;

use App\Jobs\BackupDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use RuntimeException;

class AdminIndexPage extends Component
{
    public string $backupError = '';

    public function backupDatabase()
    {
        $this->authorize('administrate');
        $this->backupError = '';

        $timestamp = Carbon::now()->format('Y-m-d-H-i-s');
        $filename = Str::of('database-backup-')->append($timestamp)->append('.tar.gz');
        $path = Str::of('backups/')->append($filename);
        try {
            BackupDatabase::dispatchSync($path);
            return Storage::download($path, $filename);
        } catch (RuntimeException $e) {
            $this->backupError = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.admin-index-page');
    }
}
