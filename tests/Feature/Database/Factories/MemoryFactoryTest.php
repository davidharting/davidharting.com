<?php

use App\Models\Memory;
use App\Support\Month;
use Tests\TestCase;

describe('startedDaysAfterOpening()', function () {
    test('starts a memory that many days after its month opened', function () {
        /** @var TestCase $this */
        $month = Month::of(2026, 9);
        $memory = Memory::factory()->forMonth($month)->startedDaysAfterOpening(2)->create();

        expect($memory->created_at->equalTo($month->opensAt()->addDays(2)))->toBeTrue();
    });
});

describe('shared()', function () {
    test('shares a few days after the memory was started', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->forMonth(Month::of(2026, 9))->shared()->create();

        expect($memory->shared_at->greaterThan($memory->created_at))->toBeTrue();
    });
});
