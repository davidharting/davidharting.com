<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of the two people who write and read memories.
 */
class MemoryParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Monthly memories is live only with exactly two participants. With fewer
     * or more, nobody can reach the feature.
     */
    public static function featureIsEnabled(): bool
    {
        return self::query()->count() === 2;
    }

    /**
     * The participant who isn't the provided user, or null when there is none.
     */
    public static function otherThan(User $user): ?User
    {
        return User::query()
            ->whereKeyNot($user->id)
            ->whereHas('memoryParticipant')
            ->first();
    }
}
