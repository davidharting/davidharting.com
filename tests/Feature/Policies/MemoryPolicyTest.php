<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\User;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The clock sits mid-October 2026: September has opened but not revealed,
 * and August's reveal deadline (Oct 1) has passed. David and Katie are the
 * two participants, which enables the feature.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->september = CalendarMonth::of(2026, 9);
    $this->august = CalendarMonth::of(2026, 8);
    [$this->david, $this->katie] = User::factory()->count(2)->create();
    MemoryParticipant::create(['user_id' => $this->david->id]);
    MemoryParticipant::create(['user_id' => $this->katie->id]);
});

describe('view()', function () {
    test('allows the author to read their own draft', function () {
        /** @var TestCase $this */
        $draft = Memory::factory()->for($this->david)->forMonth($this->september)->create();

        expect(Gate::forUser($this->david)->allows('view', $draft))->toBeTrue();
    });

    test('is not found for the other participant before the month reveals', function () {
        /** @var TestCase $this */
        $shared = Memory::factory()->for($this->david)->forMonth($this->september)->shared()->create();

        expect(Gate::forUser($this->katie)->inspect('view', $shared)->status())->toBe(404);
    });

    test('allows the other participant once the month has revealed', function () {
        /** @var TestCase $this */
        $shared = Memory::factory()->for($this->david)->forMonth($this->august)->shared(CarbonImmutable::parse('2026-09-05T12:00:00Z'))->create();

        expect(Gate::forUser($this->katie)->allows('view', $shared))->toBeTrue();
    });

    test('is not found for the other participant on a draft, even after the reveal', function () {
        /** @var TestCase $this */
        $draft = Memory::factory()->for($this->david)->forMonth($this->august)->create();

        expect(Gate::forUser($this->katie)->inspect('view', $draft)->status())->toBe(404);
    });

    test('allows the other participant once both have shared, before the deadline', function () {
        /** @var TestCase $this */
        $davids = Memory::factory()->for($this->david)->forMonth($this->september)->shared()->create();
        Memory::factory()->for($this->katie)->forMonth($this->september)->shared()->create();

        expect(Gate::forUser($this->katie)->allows('view', $davids))->toBeTrue();
    });

    test('is not found for a guest or a non-participant, even once revealed', function () {
        /** @var TestCase $this */
        $shared = Memory::factory()->for($this->david)->forMonth($this->august)->shared(CarbonImmutable::parse('2026-09-05T12:00:00Z'))->create();

        expect(Gate::inspect('view', $shared)->status())->toBe(404)
            ->and(Gate::forUser(User::factory()->create())->inspect('view', $shared)->status())->toBe(404);
    });
});

describe('create()', function () {
    test('allows a participant and is not found for a guest or a non-participant', function () {
        /** @var TestCase $this */
        $nonParticipant = User::factory()->create();

        expect(Gate::forUser($this->david)->allows('create', Memory::class))->toBeTrue()
            ->and(Gate::inspect('create', Memory::class)->status())->toBe(404)
            ->and(Gate::forUser($nonParticipant)->inspect('create', Memory::class)->status())->toBe(404);
    });
});

describe('update()', function () {
    beforeEach(function () {
        /** @var TestCase $this */
        $this->davidsMemory = Memory::factory()->for($this->david)->create();
    });

    test('allows the author', function () {
        /** @var TestCase $this */
        expect(Gate::forUser($this->david)->allows('update', $this->davidsMemory))->toBeTrue();
    });

    test('is not found for the other participant or a guest', function () {
        /** @var TestCase $this */
        expect(Gate::forUser($this->katie)->inspect('update', $this->davidsMemory)->status())->toBe(404)
            ->and(Gate::inspect('update', $this->davidsMemory)->status())->toBe(404);
    });

    test('is not found for the author while the feature is not enabled', function () {
        /** @var TestCase $this */
        MemoryParticipant::query()->where('user_id', $this->katie->id)->delete();

        expect(Gate::forUser($this->david)->inspect('update', $this->davidsMemory)->status())->toBe(404);
    });
});

describe('react()', function () {
    beforeEach(function () {
        /** @var TestCase $this */
        $this->davidsRevealed = Memory::factory()->for($this->david)->forMonth($this->august)->shared(CarbonImmutable::parse('2026-09-05T12:00:00Z'))->create();
    });

    test('allows the other participant once the month has revealed', function () {
        /** @var TestCase $this */
        expect(Gate::forUser($this->katie)->allows('react', $this->davidsRevealed))->toBeTrue();
    });

    test('is not found for the author, even once revealed', function () {
        /** @var TestCase $this */
        expect(Gate::forUser($this->david)->inspect('react', $this->davidsRevealed)->status())->toBe(404);
    });

    test('is not found for the other participant before the month reveals', function () {
        /** @var TestCase $this */
        $shared = Memory::factory()->for($this->david)->forMonth($this->september)->shared()->create();

        expect(Gate::forUser($this->katie)->inspect('react', $shared)->status())->toBe(404);
    });

    test('is not found for a guest', function () {
        /** @var TestCase $this */
        expect(Gate::inspect('react', $this->davidsRevealed)->status())->toBe(404);
    });

    test('is not found for a non-participant', function () {
        /** @var TestCase $this */
        expect(Gate::forUser(User::factory()->create())->inspect('react', $this->davidsRevealed)->status())->toBe(404);
    });
});
