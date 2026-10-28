<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Queries\ListMonthsQuery;
use App\Support\CalendarMonth;
use Tests\TestCase;

/**
 * The latest open month is September 2026. Both participants joined in
 * June, so June is the launch month.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    MemoryParticipant::factory()->count(2)->create(['created_at' => '2026-06-10T12:00:00Z']);
});

/**
 * @return list<string>
 */
function listedYearMonths(): array
{
    return (new ListMonthsQuery)->execute()
        ->map(fn (Month $month) => $month->calendarMonth->toYearMonth())
        ->all();
}

describe('execute()', function () {
    test('lists every month from launch to the latest open one, newest first, empty months included', function () {
        /** @var TestCase $this */
        expect(listedYearMonths())->toBe(['2026-09', '2026-08', '2026-07', '2026-06']);
    });

    test('adds a month before launch once either person has a memory for it, a draft included', function () {
        /** @var TestCase $this */
        Memory::factory()->forMonth(CalendarMonth::of(2019, 3))->create();
        Memory::factory()->forMonth(CalendarMonth::of(2026, 2))->create(['shared_at' => now()]);

        expect(listedYearMonths())->toBe(['2026-09', '2026-08', '2026-07', '2026-06', '2026-02', '2019-03']);
    });

    test('loads each month with its own memories', function () {
        /** @var TestCase $this */
        $march = Memory::factory()->forMonth(CalendarMonth::of(2019, 3))->create();
        $august = Memory::factory()->forMonth(CalendarMonth::of(2026, 8))->create();

        $months = (new ListMonthsQuery)->execute()
            ->mapWithKeys(fn (Month $month) => [$month->calendarMonth->toYearMonth() => $month->memories->pluck('id')->all()]);

        expect($months->all())->toBe([
            '2026-09' => [],
            '2026-08' => [$august->id],
            '2026-07' => [],
            '2026-06' => [],
            '2019-03' => [$march->id],
        ]);
    });

    test('is only the latest open month before both participants exist', function () {
        /** @var TestCase $this */
        MemoryParticipant::query()->first()->delete();

        expect(listedYearMonths())->toBe(['2026-09']);
    });

    test('lists nothing when both joined during the month in progress and nobody has written', function () {
        /** @var TestCase $this */
        MemoryParticipant::query()->update(['created_at' => '2026-10-05T12:00:00Z']);

        expect(listedYearMonths())->toBe([]);
    });
});

describe('previous()', function () {
    test('is the month before when it is listed', function () {
        /** @var TestCase $this */
        expect((new ListMonthsQuery)->previous(CalendarMonth::of(2026, 8)))->toEqual(CalendarMonth::of(2026, 7));
    });

    test('skips the unlisted months back to the nearest one written about before launch', function () {
        /** @var TestCase $this */
        Memory::factory()->forMonth(CalendarMonth::of(2019, 3))->create();

        expect((new ListMonthsQuery)->previous(CalendarMonth::of(2026, 6)))->toEqual(CalendarMonth::of(2019, 3));
    });

    test('is null for the earliest listed month', function () {
        /** @var TestCase $this */
        expect((new ListMonthsQuery)->previous(CalendarMonth::of(2026, 6)))->toBeNull();
    });
});

describe('next()', function () {
    test('skips the unlisted months up to launch', function () {
        /** @var TestCase $this */
        Memory::factory()->forMonth(CalendarMonth::of(2019, 3))->create();

        expect((new ListMonthsQuery)->next(CalendarMonth::of(2019, 3)))->toEqual(CalendarMonth::of(2026, 6));
    });

    test('is null for the latest open month', function () {
        /** @var TestCase $this */
        expect((new ListMonthsQuery)->next(CalendarMonth::of(2026, 9)))->toBeNull();
    });
});
