<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * PROTOTYPE (#251), variant B: Media Library's model under a name and table that don't clash with our Media.
 */
class MediaLibraryFile extends SpatieMedia
{
    use SoftDeletes;

    protected $table = 'media_library_files';
}
