<?php

namespace App\Support\MediaLibrary;

use App\Support\Vips;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ImageGenerators\ImageGenerator;

/**
 * PROTOTYPE (#251), variant B: Media Library's hook for files its image driver can't read.
 *
 * Media Library only reads HEIC through Imagick. Its generator runs before every conversion and hands back
 * "an image representation" that the configured driver then manipulates and re-saves. Engines:
 * - gd:   transcode HEIC to a full-size JPEG (keeping the profile and EXIF) so GD can read it; JPEGs pass through.
 * - vips: pass everything through; php-vips (FFI) reads HEIC itself.
 * - cli:  do the whole job here with vipsthumbnail, so the driver only has a correct, small JPEG to re-save.
 */
class VipsCliImageGenerator extends ImageGenerator
{
    /** @var array<string, array{int, int}> */
    public const SIZES = ['web' => [2048, 82], 'thumb' => [512, 80]];

    public function convert(string $file, ?Conversion $conversion = null): ?string
    {
        $engine = config('media-library.prototype_engine');
        $output = pathinfo($file, PATHINFO_DIRNAME).'/'.pathinfo($file, PATHINFO_FILENAME).'-'.($conversion?->getName() ?? 'base').'.jpg';

        if ($engine === 'cli' && $conversion && isset(self::SIZES[$conversion->getName()])) {
            [$maxEdge, $quality] = self::SIZES[$conversion->getName()];
            Vips::thumbnail($file, $output, $maxEdge, $quality);

            return $output;
        }

        if ($engine === 'gd' && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['heic', 'heif'])) {
            $process = Process::run(['vips', 'copy', $file, "{$output}[Q=95]"]);
            throw_if($process->failed(), RuntimeException::class, $process->errorOutput());

            return $output;
        }

        return $file;
    }

    public function requirementsAreInstalled(): bool
    {
        return true;
    }

    public function supportedExtensions(): Collection
    {
        return collect(['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif']);
    }

    public function supportedMimeTypes(): Collection
    {
        return collect(['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif']);
    }
}
