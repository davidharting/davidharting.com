<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * September 2026 opens on Oct 1 and reveals by Nov 1 at the latest. The
 * clock sits mid-October, before that deadline. August's deadline, Oct 1,
 * has already passed.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->september = CalendarMonth::of(2026, 9);
    $this->david = User::factory()->create();
    $this->katie = User::factory()->create();
});

/**
 * A null sharedAt makes the memory a draft.
 */
function monthTestMemory(User $user, CalendarMonth $month, ?string $sharedAt = null): Memory
{
    $factory = Memory::factory()->for($user)->forMonth($month);

    return ($sharedAt === null ? $factory : $factory->shared(CarbonImmutable::parse($sharedAt)))->create();
}

/**
 * The month, read from the database once these memories have been written.
 */
function monthWith(CalendarMonth $calendarMonth, Memory ...$memories): Month
{
    expect(Month::of($calendarMonth)->memories)->toHaveCount(count($memories));

    return Month::of($calendarMonth);
}

describe('revealedAt()', function () {
    test('is null while nobody has shared', function () {
        /** @var TestCase $this */
        $month = monthWith(
            $this->september,
            monthTestMemory($this->david, $this->september),
            monthTestMemory($this->katie, $this->september),
        );

        expect($month->revealedAt())->toBeNull();
    });

    test('is null while only one has shared', function () {
        /** @var TestCase $this */
        $month = monthWith(
            $this->september,
            monthTestMemory($this->david, $this->september, '2026-10-05T12:00:00Z'),
            monthTestMemory($this->katie, $this->september),
        );

        expect($month->revealedAt())->toBeNull();
    });

    test('is the second share when both shared before the deadline', function () {
        /** @var TestCase $this */
        $month = monthWith(
            $this->september,
            monthTestMemory($this->david, $this->september, '2026-10-05T12:00:00Z'),
            monthTestMemory($this->katie, $this->september, '2026-10-09T12:00:00Z'),
        );

        expect($month->revealedAt()->toIso8601String())->toBe('2026-10-09T12:00:00+00:00');
    });

    test('is the deadline once it passes with one memory unshared', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $month = monthWith(
            $august,
            monthTestMemory($this->david, $august, '2026-09-05T12:00:00Z'),
            monthTestMemory($this->katie, $august),
        );

        expect($month->revealedAt()->toIso8601String())->toBe('2026-10-01T04:00:00+00:00');
    });

    test('is the deadline when the second share came after it', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $month = monthWith(
            $august,
            monthTestMemory($this->david, $august, '2026-09-05T12:00:00Z'),
            monthTestMemory($this->katie, $august, '2026-10-04T12:00:00Z'),
        );

        expect($month->revealedAt()->toIso8601String())->toBe('2026-10-01T04:00:00+00:00');
    });

    test('is the deadline for a month nobody wrote about', function () {
        /** @var TestCase $this */
        $june = CalendarMonth::of(2026, 6);

        expect(monthWith($june)->revealedAt()->toIso8601String())->toBe('2026-08-01T04:00:00+00:00');
    });

    test('stays null until the deadline instant, then is the deadline', function () {
        /** @var TestCase $this */
        $month = monthWith($this->september, monthTestMemory($this->david, $this->september, '2026-10-05T12:00:00Z'));

        // 04:00Z is midnight Eastern (EDT) on Nov 1.
        $this->travelTo('2026-11-01T03:59:59Z');
        expect($month->revealedAt())->toBeNull();

        $this->travelTo('2026-11-01T04:00:00Z');
        expect($month->revealedAt()->toIso8601String())->toBe('2026-11-01T04:00:00+00:00');
    });
});

describe('hasRevealed()', function () {
    test('is false before the reveal', function () {
        /** @var TestCase $this */
        $month = monthWith($this->september, monthTestMemory($this->david, $this->september, '2026-10-05T12:00:00Z'));

        expect($month->hasRevealed())->toBeFalse();
    });

    test('is true after the reveal', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $month = monthWith($august, monthTestMemory($this->david, $august, '2026-09-05T12:00:00Z'));

        expect($month->hasRevealed())->toBeTrue();
    });
});

describe('isRevealed()', function () {
    test('is false for a shared memory before the reveal', function () {
        /** @var TestCase $this */
        $shared = monthTestMemory($this->david, $this->september, '2026-10-05T12:00:00Z');
        $month = monthWith($this->september, $shared);

        expect($month->isRevealed($shared))->toBeFalse();
    });

    test('is true for on-time and late shares alike once revealed', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $onTime = monthTestMemory($this->david, $august, '2026-09-05T12:00:00Z');
        $late = monthTestMemory($this->katie, $august, '2026-10-04T12:00:00Z');
        $month = monthWith($august, $onTime, $late);

        expect($month->isRevealed($onTime))->toBeTrue()
            ->and($month->isRevealed($late))->toBeTrue();
    });

    test('is false for a draft even after the reveal deadline has passed', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $draft = monthTestMemory($this->katie, $august);
        $month = monthWith(
            $august,
            monthTestMemory($this->david, $august, '2026-09-05T12:00:00Z'),
            $draft,
        );

        expect($month->hasRevealed())->toBeTrue()
            ->and($month->isRevealed($draft))->toBeFalse();
    });
});

describe('missedBy()', function () {
    test('is false for everyone before the reveal, even with nothing shared', function () {
        /** @var TestCase $this */
        $month = monthWith($this->september, monthTestMemory($this->david, $this->september));

        expect($month->missedBy($this->david))->toBeFalse()
            ->and($month->missedBy($this->katie))->toBeFalse();
    });

    test('is true after the reveal for whoever wrote nothing or left a draft', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $month = monthWith($august, monthTestMemory($this->david, $august));

        expect($month->missedBy($this->david))->toBeTrue()
            ->and($month->missedBy($this->katie))->toBeTrue();
    });

    test('is false after the reveal for whoever shared, even late', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $month = monthWith($august, monthTestMemory($this->david, $august, '2026-10-04T12:00:00Z'));

        expect($month->missedBy($this->david))->toBeFalse()
            ->and($month->missedBy($this->katie))->toBeTrue();
    });
});

describe('missed()', function () {
    test('is false before the reveal, even with nothing shared', function () {
        /** @var TestCase $this */
        expect(Month::of($this->september)->missed())->toBeFalse();
    });

    test('is true once the month revealed with nothing shared', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $month = monthWith($august, monthTestMemory($this->david, $august));

        expect($month->missed())->toBeTrue();
    });

    test('is false once either person shared', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $month = monthWith($august, monthTestMemory($this->katie, $august, '2026-09-05T12:00:00Z'));

        expect($month->missed())->toBeFalse();
    });
});

describe('memoryOf()', function () {
    test('is the user\'s memory, or null when they have none', function () {
        /** @var TestCase $this */
        $katies = monthTestMemory($this->katie, $this->september);
        $month = monthWith($this->september, $katies);

        expect($month->memoryOf($this->katie)?->is($katies))->toBeTrue()
            ->and($month->memoryOf($this->david))->toBeNull();
    });
});

describe('memories()', function () {
    test('are only that month\'s memories', function () {
        /** @var TestCase $this */
        $september = monthTestMemory($this->david, $this->september);
        monthTestMemory($this->david, CalendarMonth::of(2026, 8));

        $month = Month::of($this->september);

        expect($month->calendarMonth->equals($this->september))->toBeTrue()
            ->and($month->memories->modelKeys())->toBe([$september->id]);
    });
});

describe('of()', function () {
    test('is the row for the calendar month, from the seeded range', function () {
        /** @var TestCase $this */
        expect(Month::of(CalendarMonth::of(2016, 1))->id)->toBe('2016-01-01')
            ->and(Month::of(CalendarMonth::of(2096, 12))->id)->toBe('2096-12-01');
    });
});

describe('resolveRouteBinding()', function () {
    test('finds the month from its Y-m route key', function () {
        /** @var TestCase $this */
        $month = (new Month)->resolveRouteBinding('2026-09');

        expect($month->id)->toBe('2026-09-01')
            ->and($month->getRouteKey())->toBe('2026-09');
    });

    test('is null for a malformed month or one outside the table', function () {
        /** @var TestCase $this */
        expect((new Month)->resolveRouteBinding('2026-13'))->toBeNull()
            ->and((new Month)->resolveRouteBinding('2010-01'))->toBeNull();
    });
});

describe('latestOpen()', function () {
    test('is the month before the current one in Eastern time', function () {
        /** @var TestCase $this */
        // 02:00Z on Oct 1 is still September in Eastern time.
        $this->travelTo('2026-10-01T02:00:00Z');

        expect(Month::latestOpen()->equals(CalendarMonth::of(2026, 8)))->toBeTrue();
    });
});

describe('launch()', function () {
    test('is the month the second participant joined in, in Eastern time', function () {
        /** @var TestCase $this */
        MemoryParticipant::factory()->create(['created_at' => '2026-03-02T12:00:00Z']);
        // 02:00Z on Jul 1 is still June in Eastern time.
        MemoryParticipant::factory()->create(['created_at' => '2026-07-01T02:00:00Z']);

        expect(Month::launch())->toEqual(CalendarMonth::of(2026, 6));
    });

    test('is the latest open month before there are two participants', function () {
        /** @var TestCase $this */
        MemoryParticipant::factory()->create(['created_at' => '2026-03-02T12:00:00Z']);

        expect(Month::launch())->toEqual(CalendarMonth::of(2026, 9));
    });

    test('stays put when someone writes about an earlier month', function () {
        /** @var TestCase $this */
        MemoryParticipant::factory()->count(2)->create(['created_at' => '2026-06-10T12:00:00Z']);
        monthTestMemory($this->david, CalendarMonth::of(2019, 3), sharedAt: '2026-10-17T12:00:00Z');

        expect(Month::launch())->toEqual(CalendarMonth::of(2026, 6));
    });
});

describe('opensAt()', function () {
    test('is the moment the month is over', function () {
        /** @var TestCase $this */
        expect(monthWith($this->september)->opensAt()->toIso8601String())->toBe('2026-10-01T04:00:00+00:00');
    });
});

describe('reminderDueAt()', function () {
    test('is 9am Eastern on the day the month opens', function () {
        /** @var TestCase $this */
        expect(monthWith($this->september)->reminderDueAt()->toIso8601String())->toBe('2026-10-01T13:00:00+00:00');
    });
});

describe('nudgeDueAt()', function () {
    test('is 9am Eastern on the 15th of the month after', function () {
        /** @var TestCase $this */
        expect(monthWith($this->september)->nudgeDueAt()->toIso8601String())->toBe('2026-10-15T13:00:00+00:00');
    });
});

describe('revealDeadline()', function () {
    test('is the end of the following month', function () {
        /** @var TestCase $this */
        expect(monthWith($this->september)->revealDeadline()->toIso8601String())->toBe('2026-11-01T04:00:00+00:00');
    });
});
