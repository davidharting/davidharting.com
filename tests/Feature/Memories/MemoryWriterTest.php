<?php

use App\Memories\MemoryChangedElsewhere;
use App\Memories\MemoryWriter;
use App\Models\Memory;
use App\Models\User;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->author = User::factory()->create();
    $this->month = Month::of(2026, 9);
    $this->writer = new MemoryWriter($this->author, $this->month);
});

describe('save()', function () {
    test('starts the memory on the first save with text', function () {
        /** @var TestCase $this */
        $memory = $this->writer->save('The first cold morning.', null);

        expect($memory->body)->toBe('The first cold morning.')
            ->and($memory->isDraft())->toBeTrue()
            ->and($memory->lock_version)->toBe(0)
            ->and($memory->created_at->toIso8601String())->toBe('2026-10-18T12:00:00+00:00');
    });

    test('does not start a memory with nothing in it', function () {
        /** @var TestCase $this */
        $memory = $this->writer->save("  \n ", null);

        expect($memory)->toBeNull();
        $this->assertDatabaseCount('memories', 0);
    });

    test('updates the text and bumps the version', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->for($this->author)->forMonth($this->month)->create(['body' => 'Before']);

        $saved = $this->writer->save('After', 0);

        expect($saved->id)->toBe($memory->id)
            ->and($saved->body)->toBe('After')
            ->and($saved->lock_version)->toBe(1);
    });

    test('lets a draft be emptied', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->author)->forMonth($this->month)->create();

        $saved = $this->writer->save('', 0);

        expect($saved->body)->toBe('');
    });

    test('never shares', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->author)->forMonth($this->month)->create();

        $saved = $this->writer->save('Still a draft', 0);

        expect($saved->isDraft())->toBeTrue();
    });

    test('refuses to overwrite text saved since the writer last saw it', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->author)->forMonth($this->month)->create(['body' => 'From the phone', 'lock_version' => 3]);

        expect(fn () => $this->writer->save('From the laptop', 2))
            ->toThrow(MemoryChangedElsewhere::class);

        $this->assertDatabaseHas('memories', ['body' => 'From the phone', 'lock_version' => 3]);
    });

    test('refuses to start a memory another device already started', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->author)->forMonth($this->month)->create(['body' => 'From the phone']);

        expect(fn () => $this->writer->save('From the laptop', null))
            ->toThrow(MemoryChangedElsewhere::class);

        $this->assertDatabaseHas('memories', ['body' => 'From the phone']);
    });

    test('refuses to empty a shared memory', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->author)->forMonth($this->month)->shared()->create(['body' => 'Shared text']);

        expect(fn () => $this->writer->save(' ', 0))
            ->toThrow(ValidationException::class);

        $this->assertDatabaseHas('memories', ['body' => 'Shared text']);
    });

    test('only touches the author\'s memory for that month', function () {
        /** @var TestCase $this */
        $someoneElses = Memory::factory()->forMonth($this->month)->create(['body' => 'Not yours']);
        $otherMonth = Memory::factory()->for($this->author)->forMonth(Month::of(2026, 8))->create(['body' => 'August']);

        $this->writer->save('September', null);

        expect($someoneElses->fresh()->body)->toBe('Not yours')
            ->and($otherMonth->fresh()->body)->toBe('August');
    });
});

describe('share()', function () {
    test('saves the text and shares the memory', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->author)->forMonth($this->month)->create(['body' => 'Before']);

        $shared = $this->writer->share('Final words', 0);

        expect($shared->body)->toBe('Final words')
            ->and($shared->shared_at->toIso8601String())->toBe('2026-10-18T12:00:00+00:00');
    });

    test('starts and shares a memory in one go', function () {
        /** @var TestCase $this */
        $shared = $this->writer->share('Short and sweet', null);

        expect($shared->isShared())->toBeTrue()
            ->and($shared->body)->toBe('Short and sweet');
    });

    test('keeps the first share time when shared again', function () {
        /** @var TestCase $this */
        $memory = Memory::factory()->for($this->author)->forMonth($this->month)
            ->shared(CarbonImmutable::parse('2026-10-05T21:40:00Z'))->create();

        $shared = $this->writer->share('Edited after sharing', 0);

        expect($shared->shared_at->toIso8601String())->toBe('2026-10-05T21:40:00+00:00')
            ->and($shared->body)->toBe('Edited after sharing');
    });

    test('refuses to share nothing', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->author)->forMonth($this->month)->create();

        expect(fn () => $this->writer->share('', 0))
            ->toThrow(ValidationException::class);

        expect(Memory::first()->isDraft())->toBeTrue();
    });

    test('refuses to share over text saved on another device', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->author)->forMonth($this->month)->create(['body' => 'From the phone', 'lock_version' => 1]);

        expect(fn () => $this->writer->share('From the laptop', 0))
            ->toThrow(MemoryChangedElsewhere::class);

        expect(Memory::first())
            ->body->toBe('From the phone')
            ->isDraft()->toBeTrue();
    });
});
