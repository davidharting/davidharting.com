<?php

use App\Memories\MonthOfMemories;
use App\Models\Memory;
use App\Models\User;
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

function makeMonthOfMemories(TestCase $test, Month $month, ?Memory $viewerMemory, ?Memory $otherMemory): MonthOfMemories
{
    return new MonthOfMemories($month, $test->viewer, $test->other, $viewerMemory, $otherMemory);
}

describe('revealedAt()', function () {
    test('is null before the deadline while neither has shared', function () {
        /** @var TestCase $this */
        $september = Month::of(2026, 9);

        $monthOfMemories = makeMonthOfMemories($this, $september, memoryFor($this->viewer, $september), null);

        expect($monthOfMemories->revealedAt())->toBeNull()
            ->and($monthOfMemories->isRevealed())->toBeFalse();
    });

    test('is null before the deadline while only one has shared', function () {
        /** @var TestCase $this */
        $september = Month::of(2026, 9);

        $monthOfMemories = makeMonthOfMemories(
            $this,
            $september,
            memoryFor($this->viewer, $september),
            memoryFor($this->other, $september, sharedAt: '2026-10-05T01:00:00Z'),
        );

        expect($monthOfMemories->revealedAt())->toBeNull();
    });

    test('is the second share once both have shared', function () {
        /** @var TestCase $this */
        $september = Month::of(2026, 9);

        $monthOfMemories = makeMonthOfMemories(
            $this,
            $september,
            memoryFor($this->viewer, $september, sharedAt: '2026-10-09T01:00:00Z'),
            memoryFor($this->other, $september, sharedAt: '2026-10-05T01:00:00Z'),
        );

        expect($monthOfMemories->revealedAt()->toIso8601String())->toBe('2026-10-09T01:00:00+00:00')
            ->and($monthOfMemories->revealedAtDeadline())->toBeFalse();
    });

    test('is the deadline when one of them never shared', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);

        $monthOfMemories = makeMonthOfMemories(
            $this,
            $july,
            memoryFor($this->viewer, $july),
            memoryFor($this->other, $july, sharedAt: '2026-08-05T01:00:00Z'),
        );

        expect($monthOfMemories->revealedAt()->toIso8601String())->toBe('2026-09-01T04:00:00+00:00')
            ->and($monthOfMemories->revealedAtDeadline())->toBeTrue();
    });

    test('is the deadline when the second share came after it', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);

        $monthOfMemories = makeMonthOfMemories(
            $this,
            $july,
            memoryFor($this->viewer, $july, sharedAt: '2026-09-04T01:00:00Z'),
            memoryFor($this->other, $july, sharedAt: '2026-08-05T01:00:00Z'),
        );

        expect($monthOfMemories->revealedAt()->toIso8601String())->toBe('2026-09-01T04:00:00+00:00')
            ->and($monthOfMemories->revealedAtDeadline())->toBeTrue();
    });

    test('is the deadline for an empty month', function () {
        /** @var TestCase $this */
        $monthOfMemories = makeMonthOfMemories($this, Month::of(2026, 7), null, null);

        expect($monthOfMemories->revealedAt()->toIso8601String())->toBe('2026-09-01T04:00:00+00:00');
    });

    test('reveals at midnight Eastern on the deadline, not a second before', function () {
        /** @var TestCase $this */
        $monthOfMemories = makeMonthOfMemories($this, Month::of(2026, 9), null, null);

        $this->travelTo(CarbonImmutable::parse('2026-11-01T03:59:59Z'));
        expect($monthOfMemories->revealedAt())->toBeNull();

        $this->travelTo(CarbonImmutable::parse('2026-11-01T04:00:00Z'));
        expect($monthOfMemories->revealedAt()?->toIso8601String())->toBe('2026-11-01T04:00:00+00:00');
    });
});

describe('readableOtherMemory()', function () {
    test('is null before the reveal, even once they have shared', function () {
        /** @var TestCase $this */
        $september = Month::of(2026, 9);

        $monthOfMemories = makeMonthOfMemories($this, $september, null, memoryFor($this->other, $september, sharedAt: '2026-10-05T01:00:00Z'));

        expect($monthOfMemories->readableOtherMemory())->toBeNull();
    });

    test('is their memory once revealed', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);
        $otherMemory = memoryFor($this->other, $july, sharedAt: '2026-08-05T01:00:00Z');

        expect(makeMonthOfMemories($this, $july, null, $otherMemory)->readableOtherMemory()?->is($otherMemory))->toBeTrue();
    });

    test('is their late share as soon as it is shared', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);
        $otherMemory = memoryFor($this->other, $july, sharedAt: '2026-09-20T01:00:00Z');

        expect(makeMonthOfMemories($this, $july, null, $otherMemory)->readableOtherMemory()?->is($otherMemory))->toBeTrue();
    });

    test('is never their draft, even after the reveal', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);

        expect(makeMonthOfMemories($this, $july, null, memoryFor($this->other, $july))->readableOtherMemory())->toBeNull();
    });
});

describe('snippet()', function () {
    test('is the first line of their memory once revealed, never before', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);
        $september = Month::of(2026, 9);
        $viewerMemory = Memory::factory()->for($this->viewer)->forMonth($july)->shared(CarbonImmutable::parse('2026-08-06T01:00:00Z'))->create(['body' => "Mine first\nMine second"]);
        $otherMemory = Memory::factory()->for($this->other)->forMonth($july)->shared(CarbonImmutable::parse('2026-08-05T01:00:00Z'))->create(['body' => "  Theirs first\nTheirs second"]);
        $unrevealed = memoryFor($this->other, $september, sharedAt: '2026-10-05T01:00:00Z');

        expect(makeMonthOfMemories($this, $july, $viewerMemory, $otherMemory)->snippet())->toBe('Theirs first')
            ->and(makeMonthOfMemories($this, $september, null, $unrevealed)->snippet())->toBeNull();
    });

    test('falls back to the viewer\'s own shared memory, never their draft', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);
        $viewerMemory = Memory::factory()->for($this->viewer)->forMonth($july)->shared(CarbonImmutable::parse('2026-08-06T01:00:00Z'))->create(['body' => "Mine first\nMine second"]);

        expect(makeMonthOfMemories($this, $july, $viewerMemory, memoryFor($this->other, $july))->snippet())->toBe('Mine first')
            ->and(makeMonthOfMemories($this, Month::of(2026, 6), memoryFor($this->viewer, Month::of(2026, 6)), null)->snippet())->toBeNull();
    });
});

describe('sharedLate()', function () {
    test('is true for a share after the reveal', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);
        $viewerMemory = memoryFor($this->viewer, $july, sharedAt: '2026-09-04T01:00:00Z');
        $otherMemory = memoryFor($this->other, $july, sharedAt: '2026-08-05T01:00:00Z');
        $monthOfMemories = makeMonthOfMemories($this, $july, $viewerMemory, $otherMemory);

        expect($monthOfMemories->sharedLate($viewerMemory))->toBeTrue()
            ->and($monthOfMemories->sharedLate($otherMemory))->toBeFalse();
    });

    test('is false for drafts, missing memories and months not yet revealed', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);
        $september = Month::of(2026, 9);
        $draft = memoryFor($this->viewer, $july);
        $unrevealed = memoryFor($this->viewer, $september, sharedAt: '2026-10-05T01:00:00Z');

        expect(makeMonthOfMemories($this, $july, $draft, null)->sharedLate($draft))->toBeFalse()
            ->and(makeMonthOfMemories($this, $july, null, null)->sharedLate(null))->toBeFalse()
            ->and(makeMonthOfMemories($this, $september, $unrevealed, null)->sharedLate($unrevealed))->toBeFalse();
    });
});

describe('isEmpty()', function () {
    test('is true only when nobody has started a memory', function () {
        /** @var TestCase $this */
        $july = Month::of(2026, 7);

        expect(makeMonthOfMemories($this, $july, null, null)->isEmpty())->toBeTrue()
            ->and(makeMonthOfMemories($this, $july, null, memoryFor($this->other, $july))->isEmpty())->toBeFalse();
    });
});
