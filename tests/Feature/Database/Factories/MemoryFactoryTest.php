<?php

use App\Models\Memory;
use App\Support\CalendarMonth;
use Tests\TestCase;

describe('startedDaysAfterOpening()', function () {
    test('starts a memory that many days after its month opened', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->forMonth(CalendarMonth::of(2026, 9))->startedDaysAfterOpening(2)->create();

        // September opens at midnight Eastern on Oct 1.
        expect($memory->created_at->toIso8601String())->toBe('2026-10-03T04:00:00+00:00');
    });
});

describe('shared()', function () {
    test('shares a few days after the memory was started', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->forMonth(CalendarMonth::of(2026, 9))->shared()->create();

        expect($memory->shared_at->greaterThan($memory->created_at))->toBeTrue();
    });
});
