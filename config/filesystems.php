<?php

$localPrivateDisk = [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'serve' => true,
    // Laravel always injects its own default 'local' disk (driver local, serve => true,
    // same root, no url) underneath our config (see the note on 'disks' below). Without an
    // explicit url here, this disk would default to the same /storage URI and collide with it.
    'url' => '/storage/private',
    'visibility' => 'private',
    'throw' => false,
];

$localPublicDisk = [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
    'throw' => false,
];

$r2PrivateDisk = [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto', // R2 uses 'auto' as the region
    'bucket' => env('R2_PRIVATE_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'),
    'use_path_style_endpoint' => true, // Required for R2
    'visibility' => 'private',
    'throw' => false,
];

$r2PublicDisk = [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('R2_PUBLIC_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'),
    'url' => env('R2_PUBLIC_URL'), // e.g. https://cdn.davidharting.com
    'use_path_style_endpoint' => true,
    'visibility' => 'public',
    'throw' => false,
];

// App code should target the 'private'/'public' aliases (e.g. Storage::disk('private')),
// FILESYSTEM_DISK_PRIVATE/_PUBLIC env variables pick what disk configuration
// each alias resolves to per environment (local disk in dev, R2 elsewhere).
$privateDisk = match (env('FILESYSTEM_DISK_PRIVATE', 'local-private')) {
    'r2-private' => $r2PrivateDisk,
    default => $localPrivateDisk,
};

$publicDisk = match (env('FILESYSTEM_DISK_PUBLIC', 'local-public')) {
    'r2-public' => $r2PublicDisk,
    default => $localPublicDisk,
};

return [
    /*
     |--------------------------------------------------------------------------
     | Default Filesystem Disk
     |--------------------------------------------------------------------------
     |
     | Here you may specify the default filesystem disk that should be used
     | by the framework. The "local" disk, as well as a variety of cloud
     | based disks are available to your application. Just store away!
     |
     */

    'default' => 'private',

    /*
     |--------------------------------------------------------------------------
     | Filesystem Disks
     |--------------------------------------------------------------------------
     |
     | Here you may configure as many filesystem "disks" as you wish, and you
     | may even configure multiple disks of the same driver. Defaults have
     | been set up for each driver as an example of the required values.
     |
     | Supported Drivers: "local", "ftp", "sftp", "s3"
     |
     */

    'disks' => [
        // Illuminate\Foundation\Bootstrap\LoadConfiguration always merges the framework's own
        // bundled config/filesystems.php 'disks' as a base underneath ours (it's one of the
        // hardcoded "mergeableOptions"), so a 'local' disk (driver local, serve => true, no
        // url, root storage/app/private) exists here even though we never define one. It's
        // harmless as long as nothing else shares its default /storage URI or its root
        // (see the url on $localPrivateDisk above) — nothing in this app uses it directly.
        'private' => $privateDisk,
        'public' => $publicDisk,
    ],

    /*
     |--------------------------------------------------------------------------
     | Symbolic Links
     |--------------------------------------------------------------------------
     |
     | Here you may configure the symbolic links that will be created when the
     | `storage:link` Artisan command is executed. The array keys should be
     | the locations of the links and the values should be their targets.
     |
     */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
