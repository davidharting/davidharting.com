<?php

namespace App\Models;

use App\Enum\MemoryPhotoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PROTOTYPE (#251), variant A: one photo on a memory, with its files at predictable paths.
 */
class MemoryPhoto extends Model
{
    use SoftDeletes;

    public const VARIANTS = ['web' => [2048, 82], 'thumb' => [512, 80]];

    protected $fillable = [
        'position',
        'caption',
        'original_extension',
        'original_mime_type',
        'original_size',
        'status',
        'width',
        'height',
        'failure',
    ];

    protected $casts = [
        'status' => MemoryPhotoStatus::class,
    ];

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    public function directory(): string
    {
        return "memories/{$this->memory_id}/photos/{$this->id}";
    }

    public function path(string $variant): string
    {
        return $variant === 'original'
            ? "{$this->directory()}/original.{$this->original_extension}"
            : "{$this->directory()}/{$variant}.jpg";
    }
}
