<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
     * Monthly memories is live only with exactly two participants, once the
     * month the second joined in has ended. With fewer or more, or before
     * then, nobody can reach the feature. The participants are read once per
     * request, since every memory's policy check asks.
     */
    public static function featureIsEnabled(): bool
    {
        $joinedAt = once(fn () => self::query()->pluck('created_at'));
        $latestOpenEndedAt = Month::latestOpen()->endsAt();

        return $joinedAt->count() === 2
            && $joinedAt->every(fn (CarbonInterface $at) => $at->lessThanOrEqualTo($latestOpenEndedAt));
    }

    /**
     * When the second participant joined, or null with fewer than two.
     * Memory emails are only for months that open after this.
     */
    public static function secondJoinedAt(): ?CarbonImmutable
    {
        $joinedAt = self::query()->orderBy('created_at')->skip(1)->value('created_at');

        return $joinedAt === null ? null : CarbonImmutable::instance($joinedAt);
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
