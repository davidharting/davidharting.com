<?php

namespace App\Models;

use App\Memories\PhotoVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A photo on a memory, with an optional caption. The original and its
 * derived copies all live on the private disk.
 *
 * Its state is derived: it is ready once processing has stored its web copy and thumbnail,
 * failed once processing has given up, and processing until either happens.
 * Removing a photo soft-deletes it.
 */
class MemoryPhoto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'memory_id',
        'position',
        'caption',
        'original_path',
        'original_mime_type',
        'original_bytes',
        'web_path',
        'web_mime_type',
        'web_width',
        'web_height',
        'web_bytes',
        'thumbnail_path',
        'thumbnail_mime_type',
        'thumbnail_width',
        'thumbnail_height',
        'thumbnail_bytes',
        'failed_at',
        'failure',
    ];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    public function isReady(): bool
    {
        return $this->web_path !== null;
    }

    public function isFailed(): bool
    {
        return $this->failed_at !== null;
    }

    public function isProcessing(): bool
    {
        return ! $this->isReady() && ! $this->isFailed();
    }

    /**
     * The copy sized for reading on the month page, once the photo is ready.
     */
    public function web(): ?PhotoVariant
    {
        return $this->copy('web');
    }

    /**
     * The small copy for month tiles, once the photo is ready.
     */
    public function thumbnail(): ?PhotoVariant
    {
        return $this->copy('thumbnail');
    }

    /**
     * @param  'web'|'thumbnail'  $copy
     */
    private function copy(string $copy): ?PhotoVariant
    {
        if ($this->{"{$copy}_path"} === null) {
            return null;
        }

        return new PhotoVariant(
            path: $this->{"{$copy}_path"},
            mimeType: $this->{"{$copy}_mime_type"},
            width: $this->{"{$copy}_width"},
            height: $this->{"{$copy}_height"},
            bytes: $this->{"{$copy}_bytes"},
        );
    }
}
