<?php

namespace App\Http\Controllers;

use App\Jobs\BackupDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AdminController extends Controller
{
    public function index()
    {
        $this->authorize('administrate');

        return view('admin.index');
    }

    public function backupDatabase(Request $request)
    {
        $this->authorize('administrate');

        $timestamp = Carbon::now()->format('Y-m-d-H-i-s');
        $filename = Str::of('database-backup-')->append($timestamp)->append('.tar.gz');
        $path = Str::of('backups/')->append($filename);

        try {
            BackupDatabase::dispatchSync($path);

            return Storage::download($path, $filename);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.index')->with('backupError', trim($e->getMessage()));
        }
    }
}
