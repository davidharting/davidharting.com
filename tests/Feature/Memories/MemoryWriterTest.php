<?php

use App\Jobs\ClaimRevealEmails;
use App\Memories\MemoryChangedElsewhere;
use App\Memories\MemoryEmailKind;
use App\Memories\MemoryWriter;
use App\Models\Memory;
use App\Models\MemoryEmail;
use App\Models\MemoryParticipant;
use App\Models\MemoryPhoto;
use App\Models\User;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->author = User::factory()->create();
    $this->month = CalendarMonth::of(2026, 9);
    $this->writer = new MemoryWriter($this->author, $this->month);
    $this->authorsMemoryFactory = Memory::factory()->for($this->author)->forMonth($this->month);
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
        $memory = $this->authorsMemoryFactory->create(['body' => 'Before']);

        $saved = $this->writer->save('After', 0);

        expect($saved->id)->toBe($memory->id)
            ->and($saved->body)->toBe('After')
            ->and($saved->lock_version)->toBe(1)
            ->and($saved->isDraft())->toBeTrue();
    });

    test('deletes a draft emptied of its text', function () {
        /** @var TestCase $this */
        $this->authorsMemoryFactory->create();

        expect($this->writer->save(" \n ", 0))->toBeNull();
        $this->assertDatabaseCount('memories', 0);
    });

    test('keeps an emptied draft that has photos', function () {
        /** @var TestCase $this */
        $draft = $this->authorsMemoryFactory->create();
        MemoryPhoto::factory()->for($draft)->create();

        expect($this->writer->save('', 0)->body)->toBe('');
    });

    test('won\'t delete a draft saved on another device since the writer last saw it', function () {
        /** @var TestCase $this */
        $this->authorsMemoryFactory->create(['body' => 'From the phone', 'lock_version' => 1]);

        expect(fn () => $this->writer->save('', 0))->toThrow(MemoryChangedElsewhere::class);

        expect(Memory::first()->body)->toBe('From the phone');
    });

    test('refuses to overwrite text saved since the writer last saw it', function () {
        /** @var TestCase $this */
        $this->authorsMemoryFactory->create(['body' => 'From the phone', 'lock_version' => 3]);

        expect(fn () => $this->writer->save('From the laptop', 2))
            ->toThrow(MemoryChangedElsewhere::class);

        $this->assertDatabaseHas('memories', ['body' => 'From the phone', 'lock_version' => 3]);
    });

    test('refuses to start a memory another device already started', function () {
        /** @var TestCase $this */
        // The row already exists here, so this goes through the version check. Two
        // simultaneous first saves would instead hit the unique index in start().
        $this->authorsMemoryFactory->create(['body' => 'From the phone']);

        expect(fn () => $this->writer->save('From the laptop', null))
            ->toThrow(MemoryChangedElsewhere::class);

        $this->assertDatabaseHas('memories', ['body' => 'From the phone']);
    });

    test('refuses to empty a shared memory', function () {
        /** @var TestCase $this */
        $this->authorsMemoryFactory->shared()->create(['body' => 'Shared text']);

        expect(fn () => $this->writer->save(' ', 0))
            ->toThrow(ValidationException::class);

        $this->assertDatabaseHas('memories', ['body' => 'Shared text']);
    });

    test('lets a shared memory with photos lose its text', function () {
        /** @var TestCase $this */
        $shared = $this->authorsMemoryFactory->shared()->create(['body' => 'Shared text']);
        MemoryPhoto::factory()->for($shared)->create();

        expect($this->writer->save('', 0)->body)->toBe('');
    });

    test('refuses to empty a shared memory whose only photos were removed', function () {
        /** @var TestCase $this */
        $shared = $this->authorsMemoryFactory->shared()->create(['body' => 'Shared text']);
        MemoryPhoto::factory()->for($shared)->create()->delete();

        expect(fn () => $this->writer->save('', 0))
            ->toThrow(ValidationException::class);
    });

    test('refuses text longer than a memory can be', function () {
        /** @var TestCase $this */
        $this->authorsMemoryFactory->create(['body' => 'Before']);

        expect(fn () => $this->writer->save(str_repeat('a', MemoryWriter::MAX_LENGTH + 1), 0))
            ->toThrow(ValidationException::class);

        expect(Memory::first()->body)->toBe('Before');
    });

    test('only touches the author\'s memory for that month', function () {
        /** @var TestCase $this */
        $someoneElses = Memory::factory()->forMonth($this->month)->create(['body' => 'Not yours']);
        $otherMonth = Memory::factory()->for($this->author)->forMonth(CalendarMonth::of(2026, 8))->create(['body' => 'August']);

        $this->writer->save('September', null);

        expect($someoneElses->fresh()->body)->toBe('Not yours')
            ->and($otherMonth->fresh()->body)->toBe('August');
    });
});

describe('share()', function () {
    test('saves the text and shares the memory', function () {
        /** @var TestCase $this */
        $this->authorsMemoryFactory->create(['body' => 'Before']);

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
        $this->authorsMemoryFactory
            ->shared(CarbonImmutable::parse('2026-10-05T21:40:00Z'))->create();

        $shared = $this->writer->share('Edited after sharing', 0);

        expect($shared->shared_at->toIso8601String())->toBe('2026-10-05T21:40:00+00:00')
            ->and($shared->body)->toBe('Edited after sharing');
    });

    test('claims the reveal emails a share makes due, a few minutes after it commits', function () {
        /** @var TestCase $this */
        Notification::fake();
        MemoryParticipant::factory()->for($this->author)->create(['created_at' => '2026-08-01T12:00:00Z']);
        $other = MemoryParticipant::factory()->create(['created_at' => '2026-08-01T12:00:00Z'])->user;
        Memory::factory()->for($other)->forMonth($this->month)->shared()->create();

        $this->writer->share('Short and sweet', null);

        expect(MemoryEmail::query()->where('kind', MemoryEmailKind::Reveal)->pluck('user_id')->sort()->values()->all())
            ->toBe(collect([$this->author->id, $other->id])->sort()->values()->all());
    });

    test('delays claiming the reveal emails until the share has committed and settled', function () {
        /** @var TestCase $this */
        Queue::fake();

        $this->writer->share('Short and sweet', null);

        Queue::assertPushed(ClaimRevealEmails::class, fn (ClaimRevealEmails $job): bool => $job->month->calendarMonth->equals($this->month)
            && $job->afterCommit === true
            && $job->delay->equalTo(now()->addMinutes(MemoryWriter::REVEAL_EMAIL_DELAY_MINUTES)));
    });

    test('queues no reveal emails when sharing an already shared memory again', function () {
        /** @var TestCase $this */
        Queue::fake();
        $this->authorsMemoryFactory->shared()->create();

        $this->writer->share('Edited after sharing', 0);

        Queue::assertNotPushed(ClaimRevealEmails::class);
    });

    test('refuses to share nothing', function (bool $hasDraft) {
        /** @var TestCase $this */
        $draft = $hasDraft ? $this->authorsMemoryFactory->create() : null;

        expect(fn () => $this->writer->share('  ', $draft?->lock_version))
            ->toThrow(ValidationException::class);

        expect(Memory::query()->whereNotNull('shared_at')->exists())->toBeFalse();
    })->with([
        'over a draft' => [true],
        'before there is a memory' => [false],
    ]);

    test('shares a memory with photos and no text', function () {
        /** @var TestCase $this */
        $draft = $this->authorsMemoryFactory->create(['body' => '']);
        MemoryPhoto::factory()->for($draft)->create();

        expect($this->writer->share('', 0)->isShared())->toBeTrue();
    });

    test('refuses to share a memory whose only photos were removed', function () {
        /** @var TestCase $this */
        $draft = $this->authorsMemoryFactory->create(['body' => '']);
        MemoryPhoto::factory()->for($draft)->create()->delete();

        expect(fn () => $this->writer->share('', 0))
            ->toThrow(ValidationException::class);

        expect($draft->fresh()->isDraft())->toBeTrue();
    });

    test('refuses to share over text saved on another device', function () {
        /** @var TestCase $this */
        $this->authorsMemoryFactory->create(['body' => 'From the phone', 'lock_version' => 1]);

        expect(fn () => $this->writer->share('From the laptop', 0))
            ->toThrow(MemoryChangedElsewhere::class);

        expect(Memory::first())
            ->body->toBe('From the phone')
            ->isDraft()->toBeTrue();
    });

    test('returns the latest version, so the next save goes through', function () {
        /** @var TestCase $this */
        $this->authorsMemoryFactory->create();

        $shared = $this->writer->share('Shared', 0);

        expect($this->writer->save('Edited after sharing', $shared->lock_version)->body)->toBe('Edited after sharing');
    });
});

describe('findOrStartForPhotos()', function () {
    test('starts a memory without text when there is none', function () {
        /** @var TestCase $this */
        $memory = $this->writer->findOrStartForPhotos();

        expect($memory)
            ->wasRecentlyCreated->toBeTrue()
            ->body->toBe('')
            ->lock_version->toBe(0)
            ->isDraft()->toBeTrue();
    });

    test('returns the memory there is, leaving its version alone', function () {
        /** @var TestCase $this */
        $existing = $this->authorsMemoryFactory->create(['body' => 'Already here', 'lock_version' => 3]);

        $memory = $this->writer->findOrStartForPhotos();

        expect($memory)
            ->id->toBe($existing->id)
            ->wasRecentlyCreated->toBeFalse()
            ->lock_version->toBe(3);
    });
});
