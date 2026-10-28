<?php

use App\Memories\AddMemoryPhoto;

/*
 * Only what this app changes from Livewire's defaults. Livewire merges its
 * own config file under this one key by key, so each key here replaces the
 * whole default for that key.
 */
return [

    /*
     * wire:navigate's progress bar takes the theme's primary colour, light
     * or dark, rather than Livewire's blue.
     */
    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => 'var(--color-primary)',
    ],

    /*
     * Photos from a phone can be larger than Livewire's 12 MB default, which
     * is the reason for this key; the rest restates Livewire's defaults,
     * except the disk and the upload time.
     *
     * Temporary uploads go to the private disk (R2 outside local development),
     * so the browser uploads straight to it.
     *
     * `max_upload_time` is how many minutes the signed upload link lasts. A
     * link is made for each photo just before it is sent; a local disk checks
     * it only once the whole photo has arrived, so it allows for a 50 MB
     * photo on a slow phone connection.
     *
     * `cleanup` deletes temporary uploads older than a day whenever a new one
     * lands, but only on a local disk. On R2, uploads that never landed stay
     * under `livewire-tmp/` for good; production deliberately has no lifecycle
     * rule to expire them.
     */
    'temporary_file_upload' => [
        'disk' => 'private',
        'rules' => ['required', 'file', 'max:'.AddMemoryPhoto::MAX_KILOBYTES],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 30,
        'cleanup' => true,
    ],

];
