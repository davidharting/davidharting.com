<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileShareController extends Controller
{
    public function create()
    {
        Log::info('Received /fileshare/create request');

        return view('fileshare.create');
    }

    public function store(Request $request)
    {
        Log::info('Received store request. Using this config:', [
            'default_disk' => config('filesystems.default'),
            'available_disks' => array_keys(config('filesystems.disks')),
            'r2_private_config' => config('filesystems.disks.r2-private'),
            'environment_vars' => [
                'FILESYSTEM_DISK_PRIVATE' => env('FILESYSTEM_DISK_PRIVATE'),
                'FILESYSTEM_DISK_PUBLIC' => env('FILESYSTEM_DISK_PUBLIC'),
                'R2_ACCESS_KEY_ID' => env('R2_ACCESS_KEY_ID') ? 'SET' : 'NOT_SET',
                'R2_SECRET_ACCESS_KEY' => env('R2_SECRET_ACCESS_KEY') ? 'SET' : 'NOT_SET',
                'R2_ENDPOINT' => env('R2_ENDPOINT'),
                'R2_PRIVATE_BUCKET' => env('R2_PRIVATE_BUCKET'),
            ],
            'storage_facade_default' => Storage::getDefaultDriver(),
            'request_info' => [
                'method' => $request->method(),
                'has_file' => $request->hasFile('file'),
                'file_size' => $request->hasFile('file') ? $request->file('file')->getSize() : null,
                'file_name' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : null,
            ],

        ]);
        $path = $request->file('file')->store('fileshare', ['visibility' => 'private']);
        Log::info('File stored. Redirecting.', ['path' => $path]);

        return redirect()->route('fileshare.show', ['path' => $path]);
    }

    public function show(string $path)
    {
        Log::info("Checking file at path: $path");
        $exists = Storage::disk()->exists($path);
        Log::info('does it exist?', ['exists' => $exists]);

        if (! $exists) {
            abort(404);
        }

        $visibility = Storage::disk()->getVisibility($path);
        $size = Storage::disk()->size($path);

        return view('fileshare.show', [
            'visibility' => $visibility,
            'size' => $size,
        ]);
    }
}
