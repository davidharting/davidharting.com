<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An emoji one participant leaves on the other's revealed memory. Removing one
 * soft-deletes it, so it can be restored.
 */
class MemoryReaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'memory_id',
        'user_id',
        'emoji',
    ];

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
