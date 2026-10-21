<?php

namespace App\Memories;

/**
 * One derived copy of a memory photo, such as the web-sized copy or the
 * thumbnail, as the processing job left it on the private disk.
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
