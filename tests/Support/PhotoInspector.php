<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;

/**
 * PROTOTYPE (#251): read back what a pipeline produced, so both variants are judged the same way.
 *
 * The fixtures are stored landscape (left half orange, right half blue) with EXIF Orientation 6,
 * GPS tags and a Display P3 profile. The intended sRGB colours are orange (200,100,50) and blue (40,90,210).
 * An upright copy is portrait with orange on top; a correctly colour-managed copy reads back near those sRGB values.
 */
class PhotoInspector
{
    /**
     * A copy of a fixture as an uploaded file, so moving it never touches the fixture.
     */
    public static function upload(string $name): UploadedFile
    {
        $copy = sys_get_temp_dir().'/'.uniqid().'-'.$name;
        copy(base_path("tests/Fixtures/photos/{$name}"), $copy);

        return new UploadedFile($copy, $name, null, null, true);
    }

    /**
     * @return array{width: int, height: int, upright: bool, top: array<int, int>, bottom: array<int, int>, exif_tags: int, has_gps: bool, icc: ?string, mime: string}
     */
    public static function inspect(string $path): array
    {
        $exif = json_decode(Process::run(['exiftool', '-j', '-G1', '-a', $path])->output(), true)[0];
        $exifTags = collect($exif)->keys()->filter(fn (string $key): bool => str_starts_with($key, 'IFD0:') || str_starts_with($key, 'ExifIFD:') || str_starts_with($key, 'GPS:'));

        $width = (int) Process::run(['vipsheader', '-f', 'width', $path])->output();
        $height = (int) Process::run(['vipsheader', '-f', 'height', $path])->output();

        return [
            'width' => $width,
            'height' => $height,
            'upright' => $height > $width,
            'top' => self::pixel($path, intdiv($width, 2), intdiv($height, 8)),
            'bottom' => self::pixel($path, intdiv($width, 2), $height - intdiv($height, 8)),
            'exif_tags' => $exifTags->count(),
            'has_gps' => $exifTags->contains(fn (string $key): bool => str_starts_with($key, 'GPS:')),
            'icc' => $exif['ICC-header:ProfileDescription'] ?? $exif['ICC_Profile:ProfileDescription'] ?? null,
            'mime' => $exif['File:MIMEType'] ?? 'unknown',
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function pixel(string $path, int $x, int $y): array
    {
        return array_map('intval', preg_split('/\s+/', trim(Process::run(['vips', 'getpoint', $path, (string) $x, (string) $y])->output())));
    }

    /**
     * Within a JPEG round trip of the intended sRGB colour.
     *
     * @param  array<int, int>  $actual
     * @param  array<int, int>  $expected
     */
    public static function near(array $actual, array $expected, int $tolerance = 6): bool
    {
        return collect($expected)->every(fn (int $value, int $channel): bool => abs(($actual[$channel] ?? -999) - $value) <= $tolerance);
    }
}
