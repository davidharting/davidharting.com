<?php

use App\Models\Memory;
use App\Models\Month;
use App\Queries\ListOpenMonthsQuery;
use App\Support\CalendarMonth;
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
        Memory::factory()->forMonth(CalendarMonth::of(2026, 6))->create();
        Memory::factory()->forMonth(CalendarMonth::of(2026, 8))->create();

        $months = (new ListOpenMonthsQuery)->execute();

        expect($months->map(fn (Month $month) => $month->calendarMonth->toYearMonth())->all())
            ->toBe(['2026-09', '2026-08', '2026-07', '2026-06']);
    });

    test('loads each month with its own memories', function () {
        /** @var TestCase $this */
        $june = Memory::factory()->forMonth(CalendarMonth::of(2026, 6))->create();
        $august = Memory::factory()->forMonth(CalendarMonth::of(2026, 8))->create();

        $months = (new ListOpenMonthsQuery)->execute()
            ->mapWithKeys(fn (Month $month) => [$month->calendarMonth->toYearMonth() => $month->memories->pluck('id')->all()]);

        expect($months->all())->toBe([
            '2026-09' => [],
            '2026-08' => [$august->id],
            '2026-07' => [],
            '2026-06' => [$june->id],
        ]);
    });

    test('is only the latest open month before anyone has written', function () {
        /** @var TestCase $this */
        $months = (new ListOpenMonthsQuery)->execute();

        expect($months)->toHaveCount(1)
            ->and($months[0]->calendarMonth->equals(CalendarMonth::of(2026, 9)))->toBeTrue();
    });
});

describe('contains()', function () {
    test('is true only from the launch month to the latest open one', function () {
        /** @var TestCase $this */
        Memory::factory()->forMonth(CalendarMonth::of(2026, 6))->create();
        $query = new ListOpenMonthsQuery;

        expect($query->contains(CalendarMonth::of(2026, 6)))->toBeTrue()
            ->and($query->contains(CalendarMonth::of(2026, 7)))->toBeTrue()
            ->and($query->contains(CalendarMonth::of(2026, 9)))->toBeTrue()
            ->and($query->contains(CalendarMonth::of(2026, 5)))->toBeFalse()
            ->and($query->contains(CalendarMonth::of(2026, 10)))->toBeFalse();
    });

    test('is only the latest open month before anyone has written', function () {
        /** @var TestCase $this */
        $query = new ListOpenMonthsQuery;

        expect($query->contains(CalendarMonth::of(2026, 9)))->toBeTrue()
            ->and($query->contains(CalendarMonth::of(2026, 8)))->toBeFalse();
    });
});
