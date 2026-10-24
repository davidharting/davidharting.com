<?php

use App\Memories\Reveal;
use App\Models\Memory;
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
function revealTestMemory(User $user, CalendarMonth $month, ?string $sharedAt = null): Memory
{
    $factory = Memory::factory()->for($user)->forMonth($month);

    return ($sharedAt === null ? $factory : $factory->shared(CarbonImmutable::parse($sharedAt)))->create();
}

describe('revealedAt()', function () {
    test('is null while nobody has shared', function () {
        /** @var TestCase $this */
        $reveal = new Reveal($this->september, collect([
            revealTestMemory($this->david, $this->september),
            revealTestMemory($this->katie, $this->september),
        ]));

        expect($reveal->revealedAt())->toBeNull();
    });

    test('is null while only one has shared', function () {
        /** @var TestCase $this */
        $reveal = new Reveal($this->september, collect([
            revealTestMemory($this->david, $this->september, '2026-10-05T12:00:00Z'),
            revealTestMemory($this->katie, $this->september),
        ]));

        expect($reveal->revealedAt())->toBeNull();
    });

    test('is the second share when both shared before the deadline', function () {
        /** @var TestCase $this */
        $reveal = new Reveal($this->september, collect([
            revealTestMemory($this->david, $this->september, '2026-10-05T12:00:00Z'),
            revealTestMemory($this->katie, $this->september, '2026-10-09T12:00:00Z'),
        ]));

        expect($reveal->revealedAt()->toIso8601String())->toBe('2026-10-09T12:00:00+00:00');
    });

    test('is the deadline once it passes with one memory unshared', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $reveal = new Reveal($august, collect([
            revealTestMemory($this->david, $august, '2026-09-05T12:00:00Z'),
            revealTestMemory($this->katie, $august),
        ]));

        expect($reveal->revealedAt()->equalTo($august->revealDeadline()))->toBeTrue();
    });

    test('is the deadline when the second share came after it', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $reveal = new Reveal($august, collect([
            revealTestMemory($this->david, $august, '2026-09-05T12:00:00Z'),
            revealTestMemory($this->katie, $august, '2026-10-04T12:00:00Z'),
        ]));

        expect($reveal->revealedAt()->equalTo($august->revealDeadline()))->toBeTrue();
    });

    test('is the deadline for a month nobody wrote about', function () {
        /** @var TestCase $this */
        $june = CalendarMonth::of(2026, 6);

        expect((new Reveal($june, collect()))->revealedAt()->equalTo($june->revealDeadline()))->toBeTrue();
    });

    test('stays null until the deadline instant, then is the deadline', function () {
        /** @var TestCase $this */
        $reveal = new Reveal($this->september, collect([
            revealTestMemory($this->david, $this->september, '2026-10-05T12:00:00Z'),
        ]));

        // 04:00Z is midnight Eastern (EDT) on Nov 1.
        $this->travelTo('2026-11-01T03:59:59Z');
        expect($reveal->revealedAt())->toBeNull();

        $this->travelTo('2026-11-01T04:00:00Z');
        expect($reveal->revealedAt()->toIso8601String())->toBe('2026-11-01T04:00:00+00:00');
    });
});

describe('hasHappened()', function () {
    test('is false before the reveal', function () {
        /** @var TestCase $this */
        $reveal = new Reveal($this->september, collect([
            revealTestMemory($this->david, $this->september, '2026-10-05T12:00:00Z'),
        ]));

        expect($reveal->hasHappened())->toBeFalse();
    });

    test('is true after the reveal', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $reveal = new Reveal($august, collect([
            revealTestMemory($this->david, $august, '2026-09-05T12:00:00Z'),
        ]));

        expect($reveal->hasHappened())->toBeTrue();
    });
});

describe('hasRevealed()', function () {
    test('is false for a shared memory before the reveal', function () {
        /** @var TestCase $this */
        $shared = revealTestMemory($this->david, $this->september, '2026-10-05T12:00:00Z');
        $reveal = new Reveal($this->september, collect([$shared]));

        expect($reveal->hasRevealed($shared))->toBeFalse();
    });

    test('is true for on-time and late shares alike once revealed', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $onTime = revealTestMemory($this->david, $august, '2026-09-05T12:00:00Z');
        $late = revealTestMemory($this->katie, $august, '2026-10-04T12:00:00Z');
        $reveal = new Reveal($august, collect([$onTime, $late]));

        expect($reveal->hasRevealed($onTime))->toBeTrue()
            ->and($reveal->hasRevealed($late))->toBeTrue();
    });

    test('is false for a draft even after the reveal', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $draft = revealTestMemory($this->katie, $august);
        $reveal = new Reveal($august, collect([
            revealTestMemory($this->david, $august, '2026-09-05T12:00:00Z'),
            $draft,
        ]));

        expect($reveal->hasHappened())->toBeTrue()
            ->and($reveal->hasRevealed($draft))->toBeFalse();
    });
});

describe('revealedMemoryOf()', function () {
    test('is the user\'s memory once revealed', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $katies = revealTestMemory($this->katie, $august, '2026-09-05T12:00:00Z');
        $reveal = new Reveal($august, collect([$katies]));

        expect($reveal->revealedMemoryOf($this->katie)?->is($katies))->toBeTrue();
    });

    test('is null before the reveal', function () {
        /** @var TestCase $this */
        $reveal = new Reveal($this->september, collect([
            revealTestMemory($this->katie, $this->september, '2026-10-05T12:00:00Z'),
        ]));

        expect($reveal->revealedMemoryOf($this->katie))->toBeNull();
    });

    test('is null for a draft', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $reveal = new Reveal($august, collect([revealTestMemory($this->katie, $august)]));

        expect($reveal->revealedMemoryOf($this->katie))->toBeNull();
    });

    test('is null for a user with no memory', function () {
        /** @var TestCase $this */
        $august = CalendarMonth::of(2026, 8);
        $reveal = new Reveal($august, collect([revealTestMemory($this->katie, $august, '2026-09-05T12:00:00Z')]));

        expect($reveal->revealedMemoryOf($this->david))->toBeNull();
    });
});
