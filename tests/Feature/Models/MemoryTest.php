<?php

use App\Models\Memory;
use App\Models\MemoryReaction;
use App\Models\User;
use App\Support\Month;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

describe('month', function () {
    test('is stored as the first day and read back as a Month', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->forMonth(Month::of(2026, 9))->create();

        $this->assertDatabaseHas('memories', ['id' => $memory->id, 'month' => '2026-09-01']);
        expect($memory->fresh()->month)->toBeInstanceOf(Month::class)
            ->and($memory->fresh()->month->equals(Month::of(2026, 9)))->toBeTrue();
    });

    test('accepts a date string', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->create(['month' => '2026-08-01']);

        expect($memory->fresh()->month->label())->toBe('August 2026');
    });

    test('must be the first of the month in the database', function () {
        /** @var TestCase $this */
        $user = User::factory()->create();

        DB::table('memories')->insert([
            'user_id' => $user->id,
            'month' => '2026-09-15',
            'body' => 'Mid-month',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->throws(QueryException::class, 'memories_month_is_first_of_month');

    test('allows only one memory per person', function () {
        /** @var TestCase $this */
        $user = User::factory()->create();
        Memory::factory()->for($user)->forMonth(Month::of(2026, 9))->create();

        Memory::factory()->for($user)->forMonth(Month::of(2026, 9))->create();
    })->throws(QueryException::class, 'memories_user_id_month_unique');

    test('lets each person write one memory for the same month', function () {
        /** @var TestCase $this */
        Memory::factory()->forMonth(Month::of(2026, 9))->create();
        Memory::factory()->forMonth(Month::of(2026, 9))->create();

        expect(Memory::count())->toBe(2);
    });
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

describe('factory', function () {
    test('starts a memory after its month opened', function () {
        /** @var TestCase $this */
        $month = Month::of(2026, 9);
        $memory = Memory::factory()->forMonth($month)->startedDaysAfterOpening(2)->create();

        expect($memory->created_at->equalTo($month->opensAt()->addDays(2)))->toBeTrue();
    });

    test('starts lock_version at zero', function () {
        /** @var TestCase $this */
        expect(Memory::factory()->create()->fresh()->lock_version)->toBe(0);
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
