<?php

namespace App\Support;

use App\Support\Vips\Vips;
use App\Support\Vips\VipsFailed;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * Turns an image pasted or uploaded into a markdown editor into a copy fit
 * for the web, stores that copy, and deletes the upload.
 *
 * The upload waits in Livewire's temporary directory on the private disk,
 * so the original never reaches the public disk. The copy is a WebP no
 * larger than {@see self::MAX_EDGE} pixels on its longest edge, turned
 * upright, converted to sRGB, and stripped of EXIF, GPS and XMP metadata.
 */
final class StoreWebImage
{
    /**
     * The kinds of image the editors take: what phones, cameras and
     * screenshots save, and what libvips can read. GIFs are left out,
     * because resizing would keep only the first frame of an animation.
     *
     * @var list<string>
     */
    public const array MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    /**
     * The largest upload the editors take, in kilobytes.
     */
    public const int MAX_KILOBYTES = 25 * 1024;

    /**
     * The longest edge of the copy, in pixels. Smaller images are never enlarged.
     */
    public const int MAX_EDGE = 2048;

    /**
     * The WebP quality of the copy.
     */
    public const int QUALITY = 80;

    /**
     * Store a web copy of the upload in `$directory` on `$disk`, then delete
     * the upload whether or not that worked.
     *
     * @return string|null The copy's path on `$disk`, or null when libvips can't read the upload, so the editor shows its upload error.
     */
    public function handle(TemporaryUploadedFile $upload, string $disk, ?string $directory): ?string
    {
        $workspace = (new TemporaryDirectory)->create();

        try {
            $source = $workspace->path('original.upload');
            $this->download($upload, $source);

            $copy = $workspace->path('web.webp');

            try {
                Vips::resize($source, $copy, self::MAX_EDGE, self::QUALITY);
            } catch (VipsFailed $exception) {
                report($exception);

                return null;
            }

            $path = ltrim(($directory ?? '').'/'.Str::ulid()->toBase32().'.webp', '/');
            $stream = fopen($copy, 'r');
            $stored = Storage::disk($disk)->put($path, $stream, ['visibility' => 'public', 'mimetype' => 'image/webp']);
            fclose($stream);

            if (! $stored) {
                throw new RuntimeException("Couldn't store {$path}.");
            }

            return $path;
        } finally {
            $workspace->delete();
            $this->deleteUpload($upload);
        }
    }

    /**
     * Delete the upload and the JSON file beside it, where Livewire keeps
     * the upload's original name, type and size.
     */
    private function deleteUpload(TemporaryUploadedFile $upload): void
    {
        $path = FileUploadConfiguration::path($upload->getFilename());

        rescue(fn () => Storage::disk(FileUploadConfiguration::disk())->delete([$path, "{$path}.json"]));
    }

    /**
     * Stream the upload from wherever Livewire keeps it to a local path,
     * because libvips works on local files.
     */
    private function download(TemporaryUploadedFile $upload, string $destination): void
    {
        $source = $upload->readStream();

        if (! is_resource($source)) {
            throw new RuntimeException("Couldn't read the upload {$upload->getFilename()}.");
        }

        $target = fopen($destination, 'w');
        stream_copy_to_stream($source, $target);
        fclose($target);
        fclose($source);
    }
}
