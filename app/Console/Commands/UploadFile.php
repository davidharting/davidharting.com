<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class UploadFile extends Command
{
    protected $signature = 'file:upload
        {path : Path to the local file to upload}
        {name : Storage name without extension, slashes for directories (e.g. notes/trip/sunset)}
        {--disk=public : The filesystem disk to upload to}';

    protected $description = 'Upload a file to a storage disk. Images are EXIF-stripped, resized, optimized, and get a thumbnail.';

    public function handle(): int
    {
        $path = $this->argument('path');
        $name = $this->argument('name');
        $diskName = $this->option('disk');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $disk = Storage::disk($diskName);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = mime_content_type($path);
        $isImage = str_starts_with($mimeType, 'image/');

        if ($isImage) {
            return $this->uploadImage($path, $name, $extension, $disk);
        }

        return $this->uploadRegularFile($path, $name, $extension, $disk);
    }

    private function uploadRegularFile(string $path, string $name, string $extension, \Illuminate\Contracts\Filesystem\Filesystem $disk): int
    {
        $storagePath = "{$name}.{$extension}";
        $disk->put($storagePath, file_get_contents($path));

        $url = $disk->url($storagePath);
        $this->info("Uploaded: {$url}");

        return self::SUCCESS;
    }

    private function uploadImage(string $path, string $name, string $extension, \Illuminate\Contracts\Filesystem\Filesystem $disk): int
    {
        // Normal version: strip EXIF, constrain to max 2000px width, optimize
        $normalTmp = tempnam(sys_get_temp_dir(), 'upload_normal_').'.'.$extension;
        copy($path, $normalTmp);

        Image::load($normalTmp)
            ->fit(Fit::Max, 2000, 2000)
            ->optimize()
            ->save();

        $normalPath = "{$name}.{$extension}";
        $disk->put($normalPath, file_get_contents($normalTmp));
        unlink($normalTmp);

        // Thumbnail: scale down to fit within 300x300, no cropping
        $thumbTmp = tempnam(sys_get_temp_dir(), 'upload_thumb_').'.'.$extension;
        copy($path, $thumbTmp);

        Image::load($thumbTmp)
            ->fit(Fit::Max, 300, 300)
            ->optimize()
            ->save();

        $thumbPath = "{$name}-thumb.{$extension}";
        $disk->put($thumbPath, file_get_contents($thumbTmp));
        unlink($thumbTmp);

        $normalUrl = $disk->url($normalPath);
        $thumbUrl = $disk->url($thumbPath);

        $this->info("Normal: {$normalUrl}");
        $this->info("Thumb:  {$thumbUrl}");

        return self::SUCCESS;
    }
}
