<?php

namespace App\Support\Vips;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;

/**
 * Runs the libvips command line tools.
 */
final class Vips
{
    /**
     * Write a copy of an image that fits in a square of `$maxEdge` pixels,
     * keeping its proportions and never enlarging it. The copy's format
     * follows `$output`'s extension, such as `.jpg` or `.webp`. The copy is
     * turned upright and converted to sRGB, and `strip` drops its EXIF, GPS
     * and XMP metadata. libvips decodes HEIC through libheif. Loaders libvips
     * marks as untrusted, such as ImageMagick and PDF, are blocked.
     *
     * With `$allFrames`, every frame of an animated GIF or WebP is resized
     * and kept, so the copy stays animated. Only the GIF and WebP loaders
     * take this option, so pass it only for those formats.
     *
     * @return array{width: int, height: int}
     *
     * @throws VipsFailed when libvips can't read the image, write the copy, or finish in time.
     */
    public static function resize(string $input, string $output, int $maxEdge, int $quality, bool $allFrames = false): array
    {
        try {
            $resize = Process::timeout(90)->env(['VIPS_BLOCK_UNTRUSTED' => '1'])->run([
                'vipsthumbnail', $allFrames ? "{$input}[n=-1]" : $input,
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
