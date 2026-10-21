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

    /**
     * @param  array{path: string, mime_type: string, width: int, height: int, bytes: int}  $stored
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            path: $stored['path'],
            mimeType: $stored['mime_type'],
            width: $stored['width'],
            height: $stored['height'],
            bytes: $stored['bytes'],
        );
    }

    /**
     * @return array{path: string, mime_type: string, width: int, height: int, bytes: int}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'mime_type' => $this->mimeType,
            'width' => $this->width,
            'height' => $this->height,
            'bytes' => $this->bytes,
        ];
    }
}
