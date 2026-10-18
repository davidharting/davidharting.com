<?php

use App\Memories\MonthOfMemories;
use App\Models\Memory;
use App\Models\User;
use App\Queries\ListMonthsOfMemoriesQuery;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo(CarbonImmutable::parse('2026-10-18T12:00:00Z'));
    $this->viewer = User::factory()->create();
    $this->other = User::factory()->create();
});

describe('execute()', function () {
    test('lists every month from launch to the latest open one, newest first, empty months included', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->other)->forMonth(Month::of(2026, 6))->create();
        $viewerMemory = Memory::factory()->for($this->viewer)->forMonth(Month::of(2026, 8))->create();

        $months = (new ListMonthsOfMemoriesQuery($this->viewer, $this->other))->execute();

        expect($months->map(fn (MonthOfMemories $monthOfMemories) => $monthOfMemories->month->toYearMonth())->all())
            ->toBe(['2026-09', '2026-08', '2026-07', '2026-06'])
            ->and($months[1]->viewerMemory?->is($viewerMemory))->toBeTrue()
            ->and($months[1]->otherMemory)->toBeNull()
            ->and($months[2]->isEmpty())->toBeTrue()
            ->and($months[3]->otherMemory)->not->toBeNull();
    });

    test('is only the latest open month before anyone has written', function () {
        /** @var TestCase $this */
        $months = (new ListMonthsOfMemoriesQuery($this->viewer, $this->other))->execute();

        expect($months)->toHaveCount(1)
            ->and($months[0]->month->equals(Month::of(2026, 9)))->toBeTrue();
    });

    test('ignores memories by anyone else', function () {
        /** @var TestCase $this */
        Memory::factory()->forMonth(Month::of(2026, 1))->create();

        $months = (new ListMonthsOfMemoriesQuery($this->viewer, $this->other))->execute();

        expect($months)->toHaveCount(1);
    });
});
