<?php

use App\Jobs\SendMemoryEmail;
use App\Memories\MemoryEmailKind;
use App\Models\MemoryEmail;
use App\Models\Month;
use App\Models\User;
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
});
