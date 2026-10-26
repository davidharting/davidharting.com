<?php

use App\Jobs\SendMemoryEmail;
use App\Memories\MemoryEmailKind;
use App\Models\MemoryEmail;
use App\Models\Month;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

beforeEach(function () {
    Queue::fake();
});

describe('claim()', function () {
    test('records the email and queues it to send', function () {
        /** @var TestCase $this */
        $user = User::factory()->create();
        $month = Month::of(Month::latestOpen());

        $email = MemoryEmail::claim($user, $month, MemoryEmailKind::MonthOpened);

        expect($email)
            ->user_id->toBe($user->id)
            ->month_id->toBe($month->id)
            ->kind->toBe(MemoryEmailKind::MonthOpened)
            ->isSent()->toBeFalse();
        Queue::assertPushed(SendMemoryEmail::class, fn (SendMemoryEmail $job) => $job->email->is($email));
    });

    test('claims nothing when the same email was claimed before', function () {
        /** @var TestCase $this */
        $user = User::factory()->create();
        $month = Month::of(Month::latestOpen());
        MemoryEmail::claim($user, $month, MemoryEmailKind::MonthOpened);

        $again = MemoryEmail::claim($user, $month, MemoryEmailKind::MonthOpened);

        expect($again)->toBeNull()
            ->and(MemoryEmail::count())->toBe(1);
        Queue::assertPushed(SendMemoryEmail::class, 1);
    });

    test('claims nothing when an earlier claim failed', function () {
        /** @var TestCase $this */
        $failed = MemoryEmail::factory()->failed()->create();

        $again = MemoryEmail::claim($failed->user, $failed->month, $failed->kind);

        expect($again)->toBeNull()
            ->and(MemoryEmail::count())->toBe(1);
        Queue::assertNothingPushed();
    });

    test('leaves the email unclaimed when it cannot be queued', function () {
        /** @var TestCase $this */
        $user = User::factory()->create();
        $month = Month::of(Month::latestOpen());
        Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('The queue is down.'));

        expect(fn () => MemoryEmail::claim($user, $month, MemoryEmailKind::MonthOpened))
            ->toThrow(RuntimeException::class, 'The queue is down.');
        expect(MemoryEmail::count())->toBe(0);
    });

    test('claims the same email separately for each person', function () {
        /** @var TestCase $this */
        $month = Month::of(Month::latestOpen());
        MemoryEmail::claim(User::factory()->create(), $month, MemoryEmailKind::MonthOpened);

        $other = MemoryEmail::claim(User::factory()->create(), $month, MemoryEmailKind::MonthOpened);

        expect($other)->not->toBeNull();
        Queue::assertPushed(SendMemoryEmail::class, 2);
    });

    test('claims each kind separately', function () {
        /** @var TestCase $this */
        $user = User::factory()->create();
        $month = Month::of(Month::latestOpen());
        MemoryEmail::claim($user, $month, MemoryEmailKind::MonthOpened);

        $nudge = MemoryEmail::claim($user, $month, MemoryEmailKind::Nudge);

        expect($nudge)->not->toBeNull()
            ->and(MemoryEmail::count())->toBe(2);
        Queue::assertPushed(SendMemoryEmail::class, 2);
    });
});
