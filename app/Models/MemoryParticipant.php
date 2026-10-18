<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of the two people who write and read memories. The feature is live
 * only while there are exactly two, so every question about "the other
 * person" goes through otherThan().
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
     * The participant who isn't this user. Null when the user isn't a
     * participant or there aren't exactly two, which is how the feature
     * stays switched off.
     */
    public static function otherThan(User $user): ?User
    {
        $userIds = self::query()->pluck('user_id');

        if ($userIds->count() !== 2 || ! $userIds->contains($user->id)) {
            return null;
        }

        return User::find($userIds->first(fn (int $userId) => $userId !== $user->id));
    }
}
