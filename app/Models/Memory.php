<?php

namespace App\Models;

use App\Support\Month;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person's entry for one past month. The row exists from the first save
 * with content, so `created_at` is when it was started. A null `shared_at`
 * makes it a draft. Its reveal is derived from both memories of the month,
 * never stored.
 */
class Memory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'month',
        'body',
        'shared_at',
    ];

    protected function casts(): array
    {
        return [
            'month' => Month::class,
            'shared_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MemoryReaction::class);
    }

    public function isShared(): bool
    {
        return $this->shared_at !== null;
    }

    public function isDraft(): bool
    {
        return ! $this->isShared();
    }
}
