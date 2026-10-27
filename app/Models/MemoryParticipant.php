<?php

namespace App\Models;

use Carbon\CarbonImmutable;
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

    protected function casts(): array
    {
        return [
            'how_it_works_dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Closes the "How it works" card on the memories list for good, on every
     * device. Closing it again keeps the first time.
     */
    public function dismissHowItWorks(): void
    {
        $this->how_it_works_dismissed_at ??= now();
        $this->save();
    }

    public function hasDismissedHowItWorks(): bool
    {
        return $this->how_it_works_dismissed_at !== null;
    }

    /**
     * Monthly memories is live only with exactly two participants. With fewer
     * or more, nobody can reach the feature. Counted once per request, since
     * every memory's policy check asks.
     */
    public static function featureIsEnabled(): bool
    {
        return once(fn () => self::query()->count() === 2);
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
