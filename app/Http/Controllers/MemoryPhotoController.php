<?php

namespace App\Http\Controllers;

use App\Models\MemoryPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Serves one variant of a memory photo by redirecting to a short-lived
 * signed link on the private disk. The route only lets in viewers who may
 * see the photo's memory.
 */
class MemoryPhotoController extends Controller
{
    /**
     * How long a signed link to a photo works.
     */
    public const LINK_LIFETIME_MINUTES = 60;

    /**
     * How long the browser may reuse the redirect, and so the signed link
     * behind it, before asking again. Shorter than the link's lifetime, so a
     * reused redirect never lands on an expired link, and long enough that
     * images stay in the browser's cache while someone reads.
     */
    public const REDIRECT_MAX_AGE_SECONDS = 50 * 60;

    /**
     * @param  'web'|'thumbnail'  $variant
     */
    public function __invoke(MemoryPhoto $photo, string $variant): RedirectResponse
    {
        $photoVariant = $variant === 'web' ? $photo->web() : $photo->thumbnail();

        abort_if($photoVariant === null, 404);

        $url = Storage::disk('private')->temporaryUrl(
            $photoVariant->path,
            now()->addMinutes(self::LINK_LIFETIME_MINUTES),
            ['ResponseCacheControl' => 'private, max-age='.(self::LINK_LIFETIME_MINUTES * 60).', immutable'],
        );

        return redirect()->away($url)
            ->header('Cache-Control', 'private, max-age='.self::REDIRECT_MAX_AGE_SECONDS);
    }
}
