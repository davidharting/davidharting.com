<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * PROTOTYPE (#251): the libvips CLI call settled in #246 and #249, shared by both variants.
 *
 * vipsthumbnail decodes HEIC through libheif, auto-rotates, converts to sRGB from the embedded profile,
 * never enlarges ('>' in the size), and the `strip` save option drops EXIF/GPS/XMP.
 */
class Vips
{
    /**
     * @return array{width: int, height: int}
     */
    public static function thumbnail(string $input, string $output, int $maxEdge, int $quality): array
    {
        $process = Process::timeout(60)->run([
            'vipsthumbnail', $input,
            '--size', "{$maxEdge}x{$maxEdge}>",
            '-e', 'srgb',
            '-o', "{$output}[Q={$quality},strip]",
        ]);

        if ($process->failed()) {
            throw new RuntimeException('vipsthumbnail failed: '.trim($process->errorOutput()));
        }

        return [
            'width' => (int) Process::run(['vipsheader', '-f', 'width', $output])->output(),
            'height' => (int) Process::run(['vipsheader', '-f', 'height', $output])->output(),
        ];
    }
}
