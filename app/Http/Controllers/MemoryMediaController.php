<?php

namespace App\Http\Controllers;

use App\Models\MediaLibraryFile;
use App\Models\Memory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * PROTOTYPE (#251), variant B: the same policy check and short-lived redirect, through Media Library's URL helpers.
 */
class MemoryMediaController extends Controller
{
    public function show(MediaLibraryFile $media, string $conversion): RedirectResponse
    {
        abort_unless($media->model instanceof Memory && $media->collection_name === 'photos', 404);
        Gate::authorize('view', $media->model);

        $conversionName = $conversion === 'original' ? '' : $conversion;
        abort_unless($conversionName === '' || $media->hasGeneratedConversion($conversionName), 404);

        return redirect()->away($media->getTemporaryUrl(now()->addMinutes(10), $conversionName));
    }
}
