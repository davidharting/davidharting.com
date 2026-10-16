<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An emoji one participant leaves on the other's revealed memory. Removing one
 * deletes the row.
 */
class MemoryReaction extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Keep the offset when writing timestamps, so a time built in Eastern
     * (such as Month::opensAt()) is stored as the right instant.
     */
    protected $dateFormat = 'Y-m-d H:i:sP';

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
