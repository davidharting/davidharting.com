<?php

use App\Jobs\SendMemoryEmail;
use App\Memories\MemoryEmailKind;
use App\Models\MemoryEmail;
use App\Models\MemoryParticipant;
use App\Notifications\Memories\MonthOpened;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function () {
    $this->travelTo('2026-11-01T14:00:00Z');
    MemoryParticipant::factory()->count(2)->create();
});

function claimedEmail(MemoryEmailKind $kind = MemoryEmailKind::MonthOpened): MemoryEmail
{
    return MemoryEmail::factory()->create([
        'user_id' => MemoryParticipant::query()->first()->user_id,
        'kind' => $kind,
    ]);
}

describe('handle()', function () {
    test('sends the :dataset email and records when it went out', function (MemoryEmailKind $kind, string $notification) {
        /** @var TestCase $this */
        Notification::fake();
        $email = claimedEmail($kind);

        SendMemoryEmail::dispatch($email);

        Notification::assertSentTo($email->user, $notification, fn ($sent) => $sent->month->is($email->month));
        expect($email->fresh()->sent_at)->toEqual(now());
    })->with([
        'month open' => [MemoryEmailKind::MonthOpened, MonthOpened::class],
    ]);

    test('does nothing when the email has already gone out', function () {
        /** @var TestCase $this */
        Notification::fake();
        $email = claimedEmail();
        $email->update(['sent_at' => '2026-11-01T13:00:00Z']);

        SendMemoryEmail::dispatch($email);

        Notification::assertNothingSent();
        expect($email->fresh()->sent_at->toIso8601ZuluString())->toBe('2026-11-01T13:00:00Z');
    });

    test('clears an earlier failure once a retry sends it', function () {
        /** @var TestCase $this */
        Notification::fake();
        $email = claimedEmail();
        $email->update(['failed_at' => '2026-11-01T13:00:00Z', 'failure' => 'The mail provider was down.']);

        SendMemoryEmail::dispatch($email);

        expect($email->fresh())->toMatchArray(['failed_at' => null, 'failure' => null])
            ->and($email->fresh()->isSent())->toBeTrue();
    });
});

describe('failed()', function () {
    test('records why sending gave up', function () {
        /** @var TestCase $this */
        $email = claimedEmail();

        (new SendMemoryEmail($email))->failed(new RuntimeException('MailerSend said no.'));

        expect($email->fresh())
            ->failed_at->toEqual(now())
            ->failure->toBe('MailerSend said no.')
            ->sent_at->toBeNull();
    });
});

test('tries four times, waiting longer before each retry', function () {
    /** @var TestCase $this */
    $job = new SendMemoryEmail(claimedEmail());

    expect($job->tries)->toBe(4)
        ->and($job->backoff)->toBe([60, 300, 1800]);
});
