<?php

namespace App\Jobs;

use App\Enum\MemoryPhotoStatus;
use App\Models\MemoryPhoto;
use App\Support\Vips;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * PROTOTYPE (#251), variant A: write web.jpg and thumb.jpg next to the original.
 */
class ProcessMemoryPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public MemoryPhoto $photo) {}

    public function handle(): void
    {
        $disk = Storage::disk('private');
        $workDir = storage_path('app/vips-'.Str::random(12));
        File::ensureDirectoryExists($workDir);

        try {
            $original = "{$workDir}/".basename($this->photo->path('original'));
            file_put_contents($original, $disk->readStream($this->photo->path('original')));

            $dimensions = [];
            foreach (MemoryPhoto::VARIANTS as $variant => [$maxEdge, $quality]) {
                $output = "{$workDir}/{$variant}.jpg";
                $dimensions[$variant] = Vips::thumbnail($original, $output, $maxEdge, $quality);
                $disk->put($this->photo->path($variant), fopen($output, 'r'));
            }

            $this->photo->update([
                'status' => MemoryPhotoStatus::READY,
                'width' => $dimensions['web']['width'],
                'height' => $dimensions['web']['height'],
                'failure' => null,
            ]);
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->photo->update(['status' => MemoryPhotoStatus::FAILED, 'failure' => $exception->getMessage()]);
    }
}
