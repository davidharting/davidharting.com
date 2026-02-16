<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileShareController extends Controller
{
    public function create()
    {
        return view('fileshare.create');
    }

    public function store(Request $request)
    {
        $diskName = $request->input('disk') === 'public' ? 'public' : null;

        Log::info('Fileshare store request', [
            'disk' => $diskName ?? config('filesystems.default'),
            'file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        $path = $diskName
            ? $request->file('file')->store('fileshare', $diskName)
            : $request->file('file')->store('fileshare');

        Log::info('File stored', ['path' => $path, 'disk' => $diskName]);

        return redirect()->route('fileshare.show', [
            'path' => $path,
            'disk' => $diskName,
        ]);
    }

    public function show(Request $request, string $path)
    {
        $diskName = $request->query('disk');
        $disk = $diskName ? Storage::disk($diskName) : Storage::disk();

        if (! $disk->exists($path)) {
            abort(404);
        }

        $size = $disk->size($path);
        $url = $diskName === 'public' ? $disk->url($path) : null;

        $temporaryUrl = $diskName !== 'public'
            ? $disk->temporaryUrl($path, now()->addMinutes(5))
            : null;

        return view('fileshare.show', [
            'size' => $size,
            'url' => $url,
            'temporaryUrl' => $temporaryUrl,
            'disk' => $diskName,
        ]);
    }
}
