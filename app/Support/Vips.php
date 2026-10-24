<?php

namespace App\Support;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;

/**
 * Runs the libvips command line tools.
 */
final class Vips
{
    /**
     * Write a JPEG copy of an image that fits in a square of `$maxEdge`
     * pixels, keeping its proportions and never enlarging it. The copy is
     * turned upright and converted to sRGB, and `strip` drops its EXIF, GPS
     * and XMP metadata. libvips decodes HEIC through libheif.
     *
     * @return array{width: int, height: int}
     *
     * @throws VipsFailed when libvips can't read the image, write the copy, or finish in time.
     */
    public static function resize(string $input, string $output, int $maxEdge, int $quality): array
    {
        try {
            $resize = Process::timeout(90)->run([
                'vipsthumbnail', $input,
                '--size', "{$maxEdge}x{$maxEdge}>",
                '-e', 'srgb',
                '-o', "{$output}[Q={$quality},strip]",
            ]);
        } catch (ProcessTimedOutException) {
            throw new VipsFailed('vipsthumbnail took longer than 90 seconds.');
        }

        if ($resize->failed()) {
            throw new VipsFailed('vipsthumbnail failed: '.trim($resize->errorOutput()));
        }

        return self::size($output);
    }

    /**
     * Read an image's size from the one-line summary `vipsheader` prints,
     * such as "web.jpg: 1536x2048 uchar, 3 bands, srgb, jpegload".
     *
     * @return array{width: int, height: int}
     *
     * @throws VipsFailed when libvips can't read the image.
     */
    private static function size(string $path): array
    {
        $header = Process::timeout(10)->run(['vipsheader', $path]);

        if ($header->failed() || preg_match('/: (\d+)x(\d+) /', $header->output(), $matches) !== 1) {
            throw new VipsFailed('vipsheader failed: '.trim($header->errorOutput() ?: $header->output()));
        }

        return ['width' => (int) $matches[1], 'height' => (int) $matches[2]];
    }
}
