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
 * Its state is derived: it is ready once processing has stored its copies,
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
        'variants',
        'failed_at',
        'failure',
    ];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'failed_at' => 'datetime',
        ];
    }

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    public function isReady(): bool
    {
        return $this->variants !== null;
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
        return $this->variant('web');
    }

    /**
     * The small copy for month tiles, once the photo is ready.
     */
    public function thumbnail(): ?PhotoVariant
    {
        return $this->variant('thumbnail');
    }

    private function variant(string $name): ?PhotoVariant
    {
        $stored = $this->variants[$name] ?? null;

        if ($stored === null) {
            return null;
        }

        return PhotoVariant::fromArray($stored);
    }
}
