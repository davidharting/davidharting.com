<?php

use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use App\Support\CalendarMonth;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The clock sits mid-October 2026, so September is the latest open month.
 * David and Katie became the two participants back in March, which enables
 * the feature.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    [$this->david, $this->katie] = User::factory()->count(2)->create();
    MemoryParticipant::factory()->for($this->david)->joinedIn(CalendarMonth::of(2026, 3))->create();
    MemoryParticipant::factory()->for($this->katie)->joinedIn(CalendarMonth::of(2026, 3))->create();
});

describe('viewAny()', function () {
    test('allows a participant while there are exactly two', function () {
        /** @var TestCase $this */
        expect(Gate::forUser($this->david)->allows('viewAny', Month::class))->toBeTrue();
    });

    test('denies guests and non-participants, even admins, with a 404', function () {
        /** @var TestCase $this */
        $guest = Gate::inspect('viewAny', Month::class);
        $adminNonParticipant = Gate::forUser(User::factory()->create(['is_admin' => true]))->inspect('viewAny', Month::class);

        expect($guest->status())->toBe(404)
            ->and($adminNonParticipant->status())->toBe(404);
    });

    test('is not found for a participant while the feature is not enabled', function () {
        /** @var TestCase $this */
        MemoryParticipant::query()->where('user_id', $this->katie->id)->delete();

        expect(Gate::forUser($this->david)->inspect('viewAny', Month::class)->status())->toBe(404);
    });
});

describe('view()', function () {
    test('allows a participant into any month from January 2016 to the latest open one, listed or not', function () {
        /** @var TestCase $this */
        expect(Gate::forUser($this->david)->allows('view', Month::of(CalendarMonth::of(2016, 1))))->toBeTrue()
            ->and(Gate::forUser($this->david)->allows('view', Month::of(CalendarMonth::of(2019, 3))))->toBeTrue()
            ->and(Gate::forUser($this->david)->allows('view', Month::of(CalendarMonth::of(2026, 9))))->toBeTrue();
    });

    test('is not found for the month in progress or a future month', function () {
        /** @var TestCase $this */
        expect(Gate::forUser($this->david)->inspect('view', Month::of(CalendarMonth::of(2026, 10)))->status())->toBe(404)
            ->and(Gate::forUser($this->david)->inspect('view', Month::of(CalendarMonth::of(2027, 1)))->status())->toBe(404);
    });

    test('is not found for a guest, even in an open month', function () {
        /** @var TestCase $this */
        expect(Gate::inspect('view', Month::of(CalendarMonth::of(2026, 9)))->status())->toBe(404);
    });
});

describe('write()', function () {
    test('allows a participant in an open month, before launch included', function () {
        /** @var TestCase $this */
        expect(Gate::forUser($this->david)->allows('write', Month::of(CalendarMonth::of(2026, 9))))->toBeTrue()
            ->and(Gate::forUser($this->david)->allows('write', Month::of(CalendarMonth::of(2019, 3))))->toBeTrue();
    });

    test('is not found for the month in progress', function () {
        /** @var TestCase $this */
        expect(Gate::forUser($this->david)->inspect('write', Month::of(CalendarMonth::of(2026, 10)))->status())->toBe(404);
    });

    test('is not found for a guest, even in an open month', function () {
        /** @var TestCase $this */
        expect(Gate::inspect('write', Month::of(CalendarMonth::of(2026, 9)))->status())->toBe(404);
    });
});
