<?php

use App\Models\Memory;
use App\Models\User;
use App\Support\MemoryMonth;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * September 2026 is open and before its reveal deadline (Nov 1); July 2026 is
 * past its deadline (Sep 1, midnight Eastern, 04:00 UTC).
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo(CarbonImmutable::parse('2026-10-18T12:00:00Z'));
    $this->viewer = User::factory()->create();
    $this->other = User::factory()->create();
});

/**
 * A memory for the month, a draft unless given when it was shared.
 */
function memoryFor(User $author, Month $month, ?string $sharedAt = null): Memory
{
    $factory = Memory::factory()->for($author)->forMonth($month);

    return ($sharedAt === null ? $factory : $factory->shared(CarbonImmutable::parse($sharedAt)))->create();
}

function monthOfMemories(TestCase $test, Month $month, ?Memory $mine, ?Memory $theirs): MemoryMonth
{
    return new MemoryMonth($month, $test->viewer, $test->other, $mine, $theirs);
}

describe('revealedAt()', function () {
    test('is null before the deadline while neither has shared', function () {
        /** @var TestCase $this */
        $september = Month::of(2026, 9);

        $memoryMonth = monthOfMemories($this, $september, memoryFor($this->viewer, $september), null);

        expect($memoryMonth->revealedAt())->toBeNull()
            ->and($memoryMonth->isRevealed())->toBeFalse();
    });

    test('is null before the deadline while only one has shared', function () {
        /** @var TestCase $this */
        $september = Month::of(2026, 9);

        $memoryMonth = monthOfMemories(
            $this,
            $september,
            memoryFor($this->viewer, $september),
            memoryFor($this->other, $september, sharedAt: '2026-10-05T01:00:00Z'),
        );

        expect($memoryMonth->revealedAt())->toBeNull();
    });

    test('is the second share once both have shared', function () {
        /** @var TestCase $this */
        $september = Month::of(2026, 9);

        $memoryMonth = monthOfMemories(
            $this,
            $september,
            memoryFor($this->viewer, $september, sharedAt: '2026-10-09T01:00:00Z'),
            memoryFor($this->other, $september, sharedAt: '2026-10-05T01:00:00Z'),
        );

        expect($memoryMonth->revealedAt()->toIso8601String())->toBe('2026-10-09T01:00:00+00:00')
            ->and($memoryMonth->revealedAtDeadline())->toBeFalse();
    });

    test('is the deadline when one of them never shared', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);

        $memoryMonth = monthOfMemories(
            $this,
            $july,
            memoryFor($this->viewer, $july),
            memoryFor($this->other, $july, sharedAt: '2026-08-05T01:00:00Z'),
        );

        expect($memoryMonth->revealedAt()->toIso8601String())->toBe('2026-09-01T04:00:00+00:00')
            ->and($memoryMonth->revealedAtDeadline())->toBeTrue();
    });

    test('is the deadline when the second share came after it', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);

        $memoryMonth = monthOfMemories(
            $this,
            $july,
            memoryFor($this->viewer, $july, sharedAt: '2026-09-04T01:00:00Z'),
            memoryFor($this->other, $july, sharedAt: '2026-08-05T01:00:00Z'),
        );

        expect($memoryMonth->revealedAt()->toIso8601String())->toBe('2026-09-01T04:00:00+00:00')
            ->and($memoryMonth->revealedAtDeadline())->toBeTrue();
    });

    test('is the deadline for an empty month', function () {
        /** @var TestCase $this */
        $memoryMonth = monthOfMemories($this, Month::of(2026, 7), null, null);

        expect($memoryMonth->revealedAt()->toIso8601String())->toBe('2026-09-01T04:00:00+00:00');
    });

    test('answers for a given moment', function () {
        /** @var TestCase $this */
        $memoryMonth = monthOfMemories($this, Month::of(2026, 9), null, null);

        expect($memoryMonth->revealedAt(CarbonImmutable::parse('2026-11-01T03:59:59Z')))->toBeNull()
            ->and($memoryMonth->revealedAt(CarbonImmutable::parse('2026-11-01T04:00:00Z'))->toIso8601String())
            ->toBe('2026-11-01T04:00:00+00:00');
    });
});

describe('readableTheirs()', function () {
    test('is null before the reveal, even once they have shared', function () {
        /** @var TestCase $this */
        $september = Month::of(2026, 9);

        $memoryMonth = monthOfMemories($this, $september, null, memoryFor($this->other, $september, sharedAt: '2026-10-05T01:00:00Z'));

        expect($memoryMonth->readableTheirs())->toBeNull();
    });

    test('is their memory once revealed', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);
        $theirs = memoryFor($this->other, $july, sharedAt: '2026-08-05T01:00:00Z');

        expect(monthOfMemories($this, $july, null, $theirs)->readableTheirs()?->is($theirs))->toBeTrue();
    });

    test('is their late share as soon as it is shared', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);
        $theirs = memoryFor($this->other, $july, sharedAt: '2026-09-20T01:00:00Z');

        expect(monthOfMemories($this, $july, null, $theirs)->readableTheirs()?->is($theirs))->toBeTrue();
    });

    test('is never their draft, even after the reveal', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);

        expect(monthOfMemories($this, $july, null, memoryFor($this->other, $july))->readableTheirs())->toBeNull();
    });
});

describe('sharedLate()', function () {
    test('is true for a share after the reveal', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);
        $mine = memoryFor($this->viewer, $july, sharedAt: '2026-09-04T01:00:00Z');
        $theirs = memoryFor($this->other, $july, sharedAt: '2026-08-05T01:00:00Z');
        $memoryMonth = monthOfMemories($this, $july, $mine, $theirs);

        expect($memoryMonth->sharedLate($mine))->toBeTrue()
            ->and($memoryMonth->sharedLate($theirs))->toBeFalse();
    });

    test('is false for drafts, missing memories and months not yet revealed', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);
        $september = Month::of(2026, 9);
        $draft = memoryFor($this->viewer, $july);
        $unrevealed = memoryFor($this->viewer, $september, sharedAt: '2026-10-05T01:00:00Z');

        expect(monthOfMemories($this, $july, $draft, null)->sharedLate($draft))->toBeFalse()
            ->and(monthOfMemories($this, $july, null, null)->sharedLate(null))->toBeFalse()
            ->and(monthOfMemories($this, $september, $unrevealed, null)->sharedLate($unrevealed))->toBeFalse();
    });
});

describe('isEmpty()', function () {
    test('is true only when nobody has started a memory', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);

        expect(monthOfMemories($this, $july, null, null)->isEmpty())->toBeTrue()
            ->and(monthOfMemories($this, $july, null, memoryFor($this->other, $july))->isEmpty())->toBeFalse();
    });
});
