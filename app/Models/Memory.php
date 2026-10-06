<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PROTOTYPE (#251): a bare memory, so both photo variants have something to attach to.
 */
class Memory extends Model
{
    use HasFactory;

    public const MAX_PHOTOS = 10;

    protected $fillable = ['user_id', 'month', 'body'];

    protected $casts = [
        'month' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Variant A: hand-rolled photos, in display order.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(MemoryPhoto::class)->orderBy('position');
    }
}
