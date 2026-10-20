<?php

use App\Models\Memory;
use App\Queries\ListOpenMonthsQuery;
use App\Support\Month;
use Tests\TestCase;

/**
 * The latest open month is September 2026. Launch is the month of the
 * earliest memory.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
});

describe('execute()', function () {
    test('lists every month from launch to the latest open one, newest first, empty months included', function () {
        /** @var TestCase $this */
        Memory::factory()->forMonth(Month::of(2026, 6))->create();
        Memory::factory()->forMonth(Month::of(2026, 8))->create();

        $months = (new ListOpenMonthsQuery)->execute();

        expect($months->map(fn (Month $month) => $month->toYearMonth())->all())
            ->toBe(['2026-09', '2026-08', '2026-07', '2026-06']);
    });

    test('is only the latest open month before anyone has written', function () {
        /** @var TestCase $this */
        $months = (new ListOpenMonthsQuery)->execute();

        expect($months)->toHaveCount(1)
            ->and($months[0]->equals(Month::of(2026, 9)))->toBeTrue();
    });
});

describe('contains()', function () {
    test('is true only from the launch month to the latest open one', function () {
        /** @var TestCase $this */
        Memory::factory()->forMonth(Month::of(2026, 6))->create();
        $query = new ListOpenMonthsQuery;

        expect($query->contains(Month::of(2026, 6)))->toBeTrue()
            ->and($query->contains(Month::of(2026, 7)))->toBeTrue()
            ->and($query->contains(Month::of(2026, 9)))->toBeTrue()
            ->and($query->contains(Month::of(2026, 5)))->toBeFalse()
            ->and($query->contains(Month::of(2026, 10)))->toBeFalse();
    });

    test('is only the latest open month before anyone has written', function () {
        /** @var TestCase $this */
        $query = new ListOpenMonthsQuery;

        expect($query->contains(Month::of(2026, 9)))->toBeTrue()
            ->and($query->contains(Month::of(2026, 8)))->toBeFalse();
    });
});
