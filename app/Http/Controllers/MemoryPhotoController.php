<?php

namespace App\Http\Controllers;

use App\Enum\MemoryPhotoStatus;
use App\Models\MemoryPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * PROTOTYPE (#251), variant A: check access on every view, then 302 to a short-lived presigned URL.
 */
class MemoryPhotoController extends Controller
{
    public function show(MemoryPhoto $photo, string $variant): RedirectResponse
    {
        Gate::authorize('view', $photo->memory);

        abort_unless($variant === 'original' || $photo->status === MemoryPhotoStatus::READY, 404);

        return redirect()->away(
            Storage::disk('private')->temporaryUrl($photo->path($variant), now()->addMinutes(10))
        );
    }
}
