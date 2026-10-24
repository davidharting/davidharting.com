<?php

namespace App\Jobs;

use App\Models\MemoryPhoto;
use App\Support\Vips\Vips;
use App\Support\Vips\VipsFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\FailOnException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;

/**
 * Makes a memory photo's web variant and thumbnail from its original, and
 * stores them beside it on the private disk.
 */
class ProcessMemoryPhoto implements ShouldQueue
{
    use Queueable;

    /**
     * The longest edge, in pixels, and the JPEG quality of each variant,
     * largest first. Each variant is made from the one before it, so only the
     * first decodes the full-size original.
     *
     * @var array<'web'|'thumbnail', array{int, int}>
     */
    public const array VARIANTS = [
        'web' => [2048, 82],
        'thumbnail' => [512, 80],
    ];

    /**
     * Run the job up to three times, so a hiccup reading from or writing to
     * the private disk gets another chance.
     */
    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Seconds to wait before the second and third tries.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public MemoryPhoto $photo) {}

    /**
     * When libvips can't turn the original into a variant, the photo itself
     * is the problem (it is corrupt, or not an image), and trying again would
     * fail the same way. So fail the job at once instead of retrying.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new FailOnException([VipsFailed::class])];
    }

    /**
     * Process the photo, unless it is already ready. The original is copied
     * to a temporary directory first, because libvips works on local files.
     */
    public function handle(): void
    {
        if ($this->photo->isReady()) {
            return;
        }

        $disk = Storage::disk('private');
        $workspace = (new TemporaryDirectory)->create();

        try {
            $source = $workspace->path(basename($this->photo->original_path));
            $this->download($this->photo->original_path, $source);

            $directory = dirname($this->photo->original_path);
            $attributes = [];

            foreach (self::VARIANTS as $variant => [$maxEdge, $quality]) {
                $copy = $workspace->path("{$variant}.jpg");

                $size = Vips::resize($source, $copy, $maxEdge, $quality);

                $path = "{$directory}/{$variant}.jpg";
                $stream = fopen($copy, 'r');
                $stored = $disk->put($path, $stream);
                fclose($stream);

                if (! $stored) {
                    throw new RuntimeException("Couldn't store {$path}.");
                }

                $attributes += [
                    "{$variant}_path" => $path,
                    "{$variant}_mime_type" => 'image/jpeg',
                    "{$variant}_width" => $size['width'],
                    "{$variant}_height" => $size['height'],
                    "{$variant}_bytes" => filesize($copy),
                ];

                $source = $copy;
            }

            $this->photo->update($attributes);
        } finally {
            $workspace->delete();
        }
    }

    /**
     * Record why processing gave up, once the photo has run out of tries.
     */
    public function failed(Throwable $exception): void
    {
        $this->photo->update([
            'failed_at' => now(),
            'failure' => Str::limit($exception->getMessage(), 1000),
        ]);
    }

    /**
     * Stream a file from the private disk to a local path, without holding
     * it all in memory.
     */
    private function download(string $path, string $destination): void
    {
        $source = Storage::disk('private')->readStream($path);

        if ($source === null) {
            throw new RuntimeException("Couldn't read {$path}.");
        }

        $target = fopen($destination, 'w');
        stream_copy_to_stream($source, $target);
        fclose($target);
        fclose($source);
    }
}
