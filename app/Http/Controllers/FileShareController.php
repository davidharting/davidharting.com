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
        $path = $request->file('file')->store('fileshare', ['visibility' => 'private']);

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
