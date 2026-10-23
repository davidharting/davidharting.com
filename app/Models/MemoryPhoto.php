<?php

namespace App\Models;

use App\Memories\PhotoVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A photo on a memory, with an optional caption. The original and its
 * variants all live on the private disk.
 *
 * Its state is derived, never stored:
 *
 * - Processing: neither of the states below yet.
 * - Ready: processing has stored its web variant and thumbnail.
 * - Failed: processing gave up, and `failure` says why.
 *
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
     * A large variant, sharp enough to fill a phone or laptop screen at a
     * fraction of the original's size. Null until the photo is ready.
     */
    public function web(): ?PhotoVariant
    {
        return $this->variant('web');
    }

    /**
     * A small variant, good enough for a preview a few centimetres across.
     * Null until the photo is ready.
     */
    public function thumbnail(): ?PhotoVariant
    {
        return $this->variant('thumbnail');
    }

    /**
     * @param  'web'|'thumbnail'  $variant
     */
    private function variant(string $variant): ?PhotoVariant
    {
        if ($this->{"{$variant}_path"} === null) {
            return null;
        }

        return new PhotoVariant(
            path: $this->{"{$variant}_path"},
            mimeType: $this->{"{$variant}_mime_type"},
            width: $this->{"{$variant}_width"},
            height: $this->{"{$variant}_height"},
            bytes: $this->{"{$variant}_bytes"},
        );
    }
}
