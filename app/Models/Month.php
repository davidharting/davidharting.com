<?php

namespace App\Models;

use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One month of memories: a calendar month and the 0, 1 or 2 memories
 * written about it, with the rules for when it opens and when it reveals.
 *
 * The `months` table holds every month from 2016 to 2096 as fixed system
 * data, keyed by its first day. Which months are open is decided here and
 * in ListOpenMonthsQuery, never by which rows exist.
 */
class Month extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * The row for a calendar month.
     */
    public static function of(CalendarMonth $calendarMonth): self
    {
        return self::query()->findOrFail($calendarMonth->toDateString());
    }

    /**
     * The most recent month that has opened for drafting. The month in
     * progress is not open yet.
     */
    public static function latestOpen(): CalendarMonth
    {
        return CalendarMonth::current()->previous();
    }

    /**
     * Every memory written for the month.
     */
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class)->chaperone();
    }

    /**
     * @return Attribute<CalendarMonth, never>
     */
    protected function calendarMonth(): Attribute
    {
        return Attribute::get(fn (): CalendarMonth => CalendarMonth::parse($this->id))->shouldCache();
    }

    /**
     * When the month opens for drafting and sharing: the moment it is over.
     */
    public function opensAt(): CarbonImmutable
    {
        return $this->calendarMonth->endsAt();
    }

    /**
     * The fallback reveal: the end of the following month, whether or not both have shared.
     */
    public function revealDeadline(): CarbonImmutable
    {
        return $this->calendarMonth->next()->endsAt();
    }

    /**
     * When the month revealed, or null while it hasn't. A month reveals when
     * both have shared, or at its deadline, whichever comes first. The reveal
     * is derived from the memories and never stored.
     */
    public function revealedAt(): ?CarbonImmutable
    {
        $deadline = $this->revealDeadline();
        $shared = $this->memories->filter(fn (Memory $memory) => $memory->isShared());

        if ($shared->count() === 2) {
            $bothSharedAt = CarbonImmutable::instance($shared->max('shared_at'));

            return $bothSharedAt->min($deadline);
        }

        if (CarbonImmutable::now()->greaterThanOrEqualTo($deadline)) {
            return $deadline;
        }

        return null;
    }

    public function hasRevealed(): bool
    {
        return $this->revealedAt() !== null;
    }

    /**
     * Whether the memory is readable by someone other than its author: it is
     * shared, and the month has revealed. A memory shared after the reveal is
     * readable as soon as it is shared.
     *
     * The reveal deadline never publishes a draft. A memory still unshared
     * when the deadline passes stays its author's alone.
     */
    public function isRevealed(Memory $memory): bool
    {
        return $memory->isShared() && $this->hasRevealed();
    }

    /**
     * The user's memory for the month, or null if they haven't started one.
     */
    public function memoryOf(User $user): ?Memory
    {
        return $this->memories->firstWhere('user_id', $user->id);
    }
}
