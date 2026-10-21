<?php

namespace App\Memories;

/**
 * One variant of a memory photo, such as the web variant or the thumbnail:
 * a resized version that processing made from the original and left on the
 * private disk.
 */
final readonly class PhotoVariant
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public int $width,
        public int $height,
        public int $bytes,
    ) {}
}
