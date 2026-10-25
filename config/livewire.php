<?php

use App\Memories\AddMemoryPhoto;

/*
 * Only what this app changes from Livewire's defaults. Livewire merges its
 * own config file under this one key by key, so each key here replaces the
 * whole default for that key.
 */
return [

    /*
     * Temporary uploads go to the default disk, which is the private disk (R2
     * outside local development), so the browser uploads straight to it.
     * Photos from a phone can be larger than Livewire's 12 MB default.
     *
     * `cleanup` only clears old uploads on a local disk. On R2, uploads that
     * never landed stay under `livewire-tmp/` until a bucket lifecycle rule
     * expires them.
     */
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:'.AddMemoryPhoto::MAX_KILOBYTES],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
