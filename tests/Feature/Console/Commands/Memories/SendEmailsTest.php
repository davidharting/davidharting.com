<?php

use App\Memories\MemoryEmailKind;
use App\Models\Memory;
use App\Models\MemoryEmail;
use App\Models\MemoryParticipant;
use App\Models\User;
use App\Notifications\Memories\MonthOpened;
use App\Notifications\Memories\Nudge;
use App\Support\CalendarMonth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function () {
    Notification::fake();
});

/**
 * Two participants, both made participants at the given time.
 *
 * @return array{User, User}
 */
function participantsJoinedAt(string $at): array
{
    test()->travelTo($at);

    return [
        MemoryParticipant::factory()->create()->user,
        MemoryParticipant::factory()->create()->user,
    ];
}

/**
 * Two participants: the first joined long ago, the second at the given time.
 *
 * @return array{User, User}
 */
function secondParticipantJoinedAt(string $at): array
{
    test()->travelTo('2026-09-01T12:00:00Z');
    $first = MemoryParticipant::factory()->create()->user;

    test()->travelTo($at);
    $second = MemoryParticipant::factory()->create()->user;

    return [$first, $second];
}

function sendEmailsAt(string $at): void
{
    test()->travelTo($at);
    test()->artisan('memories:send-emails')->assertSuccessful();
}

function expectEmailedAbout(User $user, string $notification, string $month): void
{
    Notification::assertSentTo($user, $notification, fn ($sent) => $sent->month->id === "{$month}-01");
}

function sharedMemoryFor(User $user, CalendarMonth $month): Memory
{
    return Memory::factory()->for($user)->forMonth($month)->shared()->create();
}

describe('the month open reminder', function () {
    test('is not due before 9am Eastern on the 1st, in :dataset', function (string $at) {
        /** @var TestCase $this */
        participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt($at);

        Notification::assertNothingSent();
        expect(MemoryEmail::count())->toBe(0);
    })->with([
        'standard time, the day daylight saving ends' => '2026-11-01T13:59:59Z',
        'daylight time' => '2027-07-01T12:59:59Z',
    ]);

    test('goes to both participants from 9am Eastern on the 1st, in :dataset', function (string $month, string $at) {
        /** @var TestCase $this */
        [$david, $katie] = participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt($at);

        expectEmailedAbout($david, MonthOpened::class, $month);
        expectEmailedAbout($katie, MonthOpened::class, $month);
        Notification::assertCount(2);
    })->with([
        'standard time, the day daylight saving ends' => ['2026-10', '2026-11-01T14:00:00Z'],
        'daylight time' => ['2027-06', '2027-07-01T13:00:00Z'],
    ]);

    test('catches up on a missed day until the nudge is due', function () {
        /** @var TestCase $this */
        [$david] = participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt('2026-11-15T13:59:59Z');

        expectEmailedAbout($david, MonthOpened::class, '2026-10');
        Notification::assertNotSentTo($david, Nudge::class);
    });

    test('is not sent once the nudge is due', function () {
        /** @var TestCase $this */
        [$david] = participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt('2026-11-15T14:00:00Z');

        Notification::assertNotSentTo($david, MonthOpened::class);
        expectEmailedAbout($david, Nudge::class, '2026-10');
    });

    test('skips someone who has already shared', function () {
        /** @var TestCase $this */
        [$david, $katie] = participantsJoinedAt('2026-10-20T12:00:00Z');
        sharedMemoryFor($katie, CalendarMonth::of(2026, 10));

        sendEmailsAt('2026-11-01T14:00:00Z');

        expectEmailedAbout($david, MonthOpened::class, '2026-10');
        Notification::assertNotSentTo($katie, MonthOpened::class);
    });
});

describe('the nudge', function () {
    test('is not due before 9am Eastern on the 15th, in :dataset', function (string $at) {
        /** @var TestCase $this */
        [$david, $katie] = participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt($at);

        Notification::assertNotSentTo($david, Nudge::class);
        Notification::assertNotSentTo($katie, Nudge::class);
    })->with([
        'standard time' => '2026-11-15T13:59:59Z',
        'daylight time, the week after it starts' => '2027-03-15T12:59:59Z',
    ]);

    test('goes to both from 9am Eastern on the 15th when neither has shared, in :dataset', function (string $month, string $at) {
        /** @var TestCase $this */
        [$david, $katie] = participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt($at);

        expectEmailedAbout($david, Nudge::class, $month);
        expectEmailedAbout($katie, Nudge::class, $month);
        Notification::assertCount(2);
    })->with([
        'standard time' => ['2026-10', '2026-11-15T14:00:00Z'],
        'daylight time, the week after it starts' => ['2027-02', '2027-03-15T13:00:00Z'],
    ]);

    test('goes only to whoever has not shared, counting a draft as not shared', function () {
        /** @var TestCase $this */
        [$david, $katie] = participantsJoinedAt('2026-10-20T12:00:00Z');
        Memory::factory()->for($david)->forMonth(CalendarMonth::of(2026, 10))->create(['shared_at' => null]);
        sharedMemoryFor($katie, CalendarMonth::of(2026, 10));

        sendEmailsAt('2026-11-15T14:00:00Z');

        expectEmailedAbout($david, Nudge::class, '2026-10');
        Notification::assertNotSentTo($katie, Nudge::class);
    });

    test('catches up on a missed day until the month is over', function () {
        /** @var TestCase $this */
        [$david] = participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt('2026-12-01T04:59:59Z');

        expectEmailedAbout($david, Nudge::class, '2026-10');
    });

    test('is not sent once the next month opens, before its reminder is due', function () {
        /** @var TestCase $this */
        participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt('2026-12-01T05:00:00Z');

        Notification::assertNothingSent();
    });
});

describe('who gets emails', function () {
    test('nobody while there is only one participant', function () {
        /** @var TestCase $this */
        $this->travelTo('2026-10-20T12:00:00Z');
        MemoryParticipant::factory()->create();

        sendEmailsAt('2026-11-01T14:00:00Z');

        Notification::assertNothingSent();
    });

    test('nobody about a month that opened before the second participant joined', function () {
        /** @var TestCase $this */
        secondParticipantJoinedAt('2026-11-02T12:00:00Z');

        sendEmailsAt('2026-11-15T14:00:00Z');

        Notification::assertNothingSent();
    });

    test('both participants about a month that opens the moment the second joins', function () {
        /** @var TestCase $this */
        [$first, $second] = secondParticipantJoinedAt('2026-12-01T05:00:00Z');

        sendEmailsAt('2026-12-01T14:00:00Z');

        expectEmailedAbout($first, MonthOpened::class, '2026-11');
        expectEmailedAbout($second, MonthOpened::class, '2026-11');
    });

    test('never someone who is not a participant', function () {
        /** @var TestCase $this */
        participantsJoinedAt('2026-10-20T12:00:00Z');
        $frodo = User::factory()->create();

        sendEmailsAt('2026-11-01T14:00:00Z');

        Notification::assertNotSentTo($frodo, MonthOpened::class);
    });
});

describe('running it again', function () {
    test('sends each email once', function () {
        /** @var TestCase $this */
        [$david, $katie] = participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt('2026-11-01T14:00:00Z');
        sendEmailsAt('2026-11-01T14:00:00Z');
        sendEmailsAt('2026-11-02T14:00:00Z');

        Notification::assertSentToTimes($david, MonthOpened::class, 1);
        Notification::assertSentToTimes($katie, MonthOpened::class, 1);
        expect(MemoryEmail::count())->toBe(2);
    });

    test('does not try again after an email failed', function () {
        /** @var TestCase $this */
        [$david] = participantsJoinedAt('2026-10-20T12:00:00Z');
        MemoryEmail::factory()->for($david)->failed()->create([
            'month_id' => '2026-10-01',
            'kind' => MemoryEmailKind::MonthOpened,
        ]);

        sendEmailsAt('2026-11-02T14:00:00Z');

        Notification::assertNotSentTo($david, MonthOpened::class);
        expect(MemoryEmail::query()->whereBelongsTo($david)->count())->toBe(1);
    });
});

describe('its output', function () {
    test('says how many emails it queued: :dataset', function (int $sharedAlready, string $output) {
        /** @var TestCase $this */
        $participants = participantsJoinedAt('2026-10-20T12:00:00Z');
        collect($participants)->take($sharedAlready)->each(fn (User $user) => sharedMemoryFor($user, CalendarMonth::of(2026, 10)));
        $this->travelTo('2026-11-01T14:00:00Z');

        $this->artisan('memories:send-emails')
            ->expectsOutputToContain($output)
            ->assertSuccessful();
    })->with([
        'two' => [0, 'Queued 2 emails.'],
        'one' => [1, 'Queued 1 email.'],
        'none' => [2, 'Queued 0 emails.'],
    ]);
});
