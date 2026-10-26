<?php

use App\Jobs\SendShareNotificationsIfNeeded;
use App\Models\Memory;
use App\Models\MemoryEmail;
use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use App\Notifications\Memories\Revealed;
use App\Support\CalendarMonth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    Notification::fake();
    $this->travelTo('2026-10-20T12:00:00Z');
    $this->david = MemoryParticipant::factory()->create()->user;
    $this->katie = MemoryParticipant::factory()->create()->user;
});

/**
 * October 2026, the first month both participants were around for. It
 * reveals at the latest at the end of November, Eastern time.
 */
function octoberMonth(): Month
{
    return Month::of(CalendarMonth::of(2026, 10));
}

function shareOctoberMemory(User $author, string $sharedAt): Memory
{
    return Memory::factory()->for($author)->forMonth(CalendarMonth::of(2026, 10))->create([
        'created_at' => '2026-11-02T12:00:00Z',
        'shared_at' => $sharedAt,
    ]);
}

function claimOctoberRevealsAt(string $at): void
{
    test()->travelTo($at);
    SendShareNotificationsIfNeeded::dispatch(octoberMonth());
}

/**
 * Every claimed email about October, as "<recipient>: <kind>".
 *
 * @return list<string>
 */
function octoberEmails(): array
{
    $names = [test()->david->id => 'David', test()->katie->id => 'Katie'];

    return MemoryEmail::query()
        ->whereBelongsTo(octoberMonth())
        ->orderBy('id')
        ->get()
        ->map(fn (MemoryEmail $email): string => "{$names[$email->user_id]}: {$email->kind->value}")
        ->all();
}

describe('handle()', function () {
    test('claims nothing while only one person has shared before the deadline', function () {
        /** @var TestCase $this */
        shareOctoberMemory($this->katie, '2026-11-05T12:00:00Z');

        claimOctoberRevealsAt('2026-11-05T12:00:00Z');

        expect(octoberEmails())->toBe([]);
    });

    test('claims the reveal for both once both have shared', function () {
        /** @var TestCase $this */
        shareOctoberMemory($this->katie, '2026-11-05T12:00:00Z');
        shareOctoberMemory($this->david, '2026-11-06T12:00:00Z');

        claimOctoberRevealsAt('2026-11-06T12:00:00Z');

        expect(octoberEmails())->toBe(['David: reveal', 'Katie: reveal']);
    });

    test('after the deadline, a share tells the other person their memory is there', function () {
        /** @var TestCase $this */
        shareOctoberMemory($this->katie, '2026-12-03T12:00:00Z');

        claimOctoberRevealsAt('2026-12-03T12:00:00Z');

        expect(octoberEmails())->toBe(['David: reveal']);
    });

    test('claims each email only once however often it runs', function () {
        /** @var TestCase $this */
        shareOctoberMemory($this->katie, '2026-11-05T12:00:00Z');
        shareOctoberMemory($this->david, '2026-11-06T12:00:00Z');

        claimOctoberRevealsAt('2026-11-06T12:00:00Z');
        claimOctoberRevealsAt('2026-11-07T12:00:00Z');

        expect(octoberEmails())->toBe(['David: reveal', 'Katie: reveal']);
    });

    test('claims nothing for a month that opened before both participants existed', function () {
        /** @var TestCase $this */
        // Both joined on October 20, so September had already opened.
        $september = Month::of(CalendarMonth::of(2026, 9));
        Memory::factory()->for($this->katie)->forMonth($september->calendarMonth)->shared()->create();
        Memory::factory()->for($this->david)->forMonth($september->calendarMonth)->shared()->create();

        SendShareNotificationsIfNeeded::dispatch($september);

        expect(MemoryEmail::count())->toBe(0);
    });

    test('claims nothing without two participants', function () {
        /** @var TestCase $this */
        MemoryParticipant::query()->whereBelongsTo($this->katie)->delete();
        shareOctoberMemory($this->david, '2026-11-05T12:00:00Z');
        shareOctoberMemory($this->katie, '2026-11-06T12:00:00Z');

        claimOctoberRevealsAt('2026-11-06T12:00:00Z');

        expect(octoberEmails())->toBe([]);
    });

    test('sends each email it claims', function () {
        /** @var TestCase $this */
        shareOctoberMemory($this->katie, '2026-12-03T12:00:00Z');

        claimOctoberRevealsAt('2026-12-03T12:00:00Z');

        Notification::assertSentTo($this->david, Revealed::class);
        Notification::assertCount(1);
    });
});
