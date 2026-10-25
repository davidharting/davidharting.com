<?php

use App\Jobs\SendMemoryEmail;
use App\Memories\MemoryEmailKind;
use App\Models\MemoryEmail;
use App\Notifications\Memories\MonthOpened;
use App\Notifications\Memories\Nudge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function () {
    $this->travelTo('2026-11-01T14:00:00Z');
});

describe('handle()', function () {
    test('sends the :dataset email to its recipient and records when it went out', function (MemoryEmailKind $kind, string $notification) {
        /** @var TestCase $this */
        Notification::fake();
        $email = MemoryEmail::factory()->create(['kind' => $kind]);

        SendMemoryEmail::dispatch($email);

        Notification::assertSentTo($email->user, $notification, fn ($sent) => $sent->month->is($email->month));
        Notification::assertCount(1);
        expect($email->fresh()->sent_at)->toEqual(now());
    })->with([
        'month open' => [MemoryEmailKind::MonthOpened, MonthOpened::class],
        'nudge' => [MemoryEmailKind::Nudge, Nudge::class],
    ]);

    test('does nothing when the email has already gone out', function () {
        /** @var TestCase $this */
        Notification::fake();
        $email = MemoryEmail::factory()->sent(CarbonImmutable::parse('2026-11-01T13:00:00Z'))->create();

        SendMemoryEmail::dispatch($email);

        Notification::assertNothingSent();
        expect($email->fresh()->sent_at->toIso8601ZuluString())->toBe('2026-11-01T13:00:00Z');
    });

    test('leaves the email unsent when the mail provider errors, so a retry can send it', function () {
        /** @var TestCase $this */
        Notification::shouldReceive('sendNow')->andThrow(new RuntimeException('MailerSend is down.'));
        $email = MemoryEmail::factory()->create();

        expect(fn () => (new SendMemoryEmail($email))->handle())->toThrow(RuntimeException::class, 'MailerSend is down.');

        expect($email->fresh()->isSent())->toBeFalse();
    });

    test('sends a failed email when it is retried, and clears the failure', function () {
        /** @var TestCase $this */
        Notification::fake();
        $email = MemoryEmail::factory()->failed()->create();

        SendMemoryEmail::dispatch($email);

        Notification::assertSentTo($email->user, MonthOpened::class);
        $email->refresh();
        expect($email->isSent())->toBeTrue()
            ->and($email->failed_at)->toBeNull()
            ->and($email->failure)->toBeNull();
    });
});

describe('failed()', function () {
    test('records why sending gave up', function () {
        /** @var TestCase $this */
        $email = MemoryEmail::factory()->create();

        (new SendMemoryEmail($email))->failed(new RuntimeException('MailerSend said no.'));

        expect($email->fresh())
            ->failed_at->toEqual(now())
            ->failure->toBe('MailerSend said no.')
            ->sent_at->toBeNull();
    });
});
