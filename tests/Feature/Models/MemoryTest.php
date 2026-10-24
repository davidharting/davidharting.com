<?php

use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Models\MemoryReaction;
use App\Models\User;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

describe('month()', function () {
    test('belongs to its month\'s row', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->forMonth(CalendarMonth::of(2026, 9))->create();

        $this->assertDatabaseHas('memories', ['id' => $memory->id, 'month_id' => '2026-09-01']);
        expect($memory->fresh()->month->calendarMonth->equals(CalendarMonth::of(2026, 9)))->toBeTrue();
    });

    test('must be a month in the months table', function () {
        /** @var TestCase $this */
        $user = User::factory()->create();

        DB::table('memories')->insert([
            'user_id' => $user->id,
            'month_id' => '2026-09-15',
            'body' => 'Mid-month',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->throws(QueryException::class, 'memories_month_id_foreign');

    test('allows only one memory per person', function () {
        /** @var TestCase $this */
        $user = User::factory()->create();
        Memory::factory()->for($user)->forMonth(CalendarMonth::of(2026, 9))->create();

        Memory::factory()->for($user)->forMonth(CalendarMonth::of(2026, 9))->create();
    })->throws(QueryException::class, 'memories_user_id_month_id_unique');

    test('lets each person write one memory for the same month', function () {
        /** @var TestCase $this */
        Memory::factory()->forMonth(CalendarMonth::of(2026, 9))->create();
        Memory::factory()->forMonth(CalendarMonth::of(2026, 9))->create();

        expect(Memory::count())->toBe(2);
    });
});

describe('shared_at', function () {
    test('cannot be before the memory was started', function () {
        /** @var TestCase $this */
        // Started the day after September opened on Oct 1, but shared before then.
        Memory::factory()->forMonth(CalendarMonth::of(2026, 9))->shared(CarbonImmutable::parse('2026-09-30T12:00:00Z'))->create();
    })->throws(QueryException::class, 'memories_shared_after_started');
});

describe('isShared()', function () {
    test('is false for a draft', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();

        expect($memory->isShared())->toBeFalse()
            ->and($memory->isDraft())->toBeTrue();
    });

    test('is true once shared', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->shared()->create();

        expect($memory->isShared())->toBeTrue()
            ->and($memory->isDraft())->toBeFalse();
    });
});

describe('reactions()', function () {
    test('allows the same person several different emoji', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->shared()->create();
        $reactor = User::factory()->create();

        MemoryReaction::factory()->for($memory)->for($reactor)->create(['emoji' => '❤️']);
        MemoryReaction::factory()->for($memory)->for($reactor)->create(['emoji' => '😂']);

        expect($memory->reactions()->count())->toBe(2);
    });

    test('allows the same emoji only once per person', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->shared()->create();
        $reactor = User::factory()->create();

        MemoryReaction::factory()->for($memory)->for($reactor)->create(['emoji' => '❤️']);
        MemoryReaction::factory()->for($memory)->for($reactor)->create(['emoji' => '❤️']);
    })->throws(QueryException::class, 'memory_reactions_memory_id_user_id_emoji_unique');
});

describe('user', function () {
    test('cannot be deleted while they have memories', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();

        $memory->user->delete();
    })->throws(QueryException::class);
});

describe('photos()', function () {
    test('lists photos in their chosen order', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();
        $third = MemoryPhoto::factory()->for($memory)->create(['position' => 2]);
        $first = MemoryPhoto::factory()->for($memory)->create(['position' => 0]);
        $second = MemoryPhoto::factory()->for($memory)->create(['position' => 1]);

        expect($memory->photos->modelKeys())->toBe([$first->id, $second->id, $third->id]);
    });

    test('leaves out removed photos', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();
        $kept = MemoryPhoto::factory()->for($memory)->create(['position' => 0]);
        MemoryPhoto::factory()->for($memory)->create(['position' => 1])->delete();

        expect($memory->photos->modelKeys())->toBe([$kept->id]);
    });

    test('keeps photos sharing a position in the order they were added', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();
        $earlier = MemoryPhoto::factory()->for($memory)->create(['position' => 0]);
        $later = MemoryPhoto::factory()->for($memory)->create(['position' => 0]);

        expect($memory->photos->modelKeys())->toBe([$earlier->id, $later->id]);
    });

    test('leaves out photos on other memories', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create();
        $own = MemoryPhoto::factory()->for($memory)->create();
        MemoryPhoto::factory()->create();

        expect($memory->photos->modelKeys())->toBe([$own->id]);
    });
});
