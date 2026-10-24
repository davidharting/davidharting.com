<?php

use App\Memories\MemoryEmailKind;
use App\Models\Memory;
use App\Models\MemoryEmail;
use App\Models\MemoryParticipant;
use App\Models\User;
use App\Notifications\Memories\MonthOpened;
use App\Notifications\Memories\Nudge;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function () {
    Notification::fake();
});

/**
 * David and Katie, both made participants at the given time.
 *
 * @return array{User, User}
 */
function participantsJoinedAt(string $at): array
{
    test()->travelTo($at);

    $david = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'David']))->create()->user;
    $katie = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'Katie']))->create()->user;

    return [$david, $katie];
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

describe('the month open reminder', function () {
    test('is not due before 9am Eastern on the 1st, in :dataset', function (string $month, string $at) {
        /** @var TestCase $this */
        participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt($at);

        Notification::assertNothingSent();
        expect(MemoryEmail::count())->toBe(0);
    })->with([
        'standard time, the day daylight saving ends' => ['2026-10', '2026-11-01T13:59:59Z'],
        'daylight time' => ['2027-06', '2027-07-01T12:59:59Z'],
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
        Memory::factory()->for($katie)->forMonth(CalendarMonth::of(2026, 10))->shared(CarbonImmutable::parse('2026-11-01T12:00:00Z'))->create(['created_at' => '2026-11-01T11:00:00Z']);

        sendEmailsAt('2026-11-01T14:00:00Z');

        expectEmailedAbout($david, MonthOpened::class, '2026-10');
        Notification::assertNotSentTo($katie, MonthOpened::class);
    });
});

describe('the nudge', function () {
    test('is not due before 9am Eastern on the 15th, in :dataset', function (string $month, string $at) {
        /** @var TestCase $this */
        [$david, $katie] = participantsJoinedAt('2026-10-20T12:00:00Z');
        MemoryEmail::factory()->for($david)->sent()->create(['month_id' => "{$month}-01"]);
        MemoryEmail::factory()->for($katie)->sent()->create(['month_id' => "{$month}-01"]);

        sendEmailsAt($at);

        Notification::assertNothingSent();
    })->with([
        'standard time' => ['2026-10', '2026-11-15T13:59:59Z'],
        'daylight time, the week after it starts' => ['2027-02', '2027-03-15T12:59:59Z'],
    ]);

    test('goes to both from 9am Eastern on the 15th when neither has shared, in :dataset', function (string $month, string $at) {
        /** @var TestCase $this */
        [$david, $katie] = participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt($at);

        expectEmailedAbout($david, Nudge::class, $month);
        expectEmailedAbout($katie, Nudge::class, $month);
    })->with([
        'standard time' => ['2026-10', '2026-11-15T14:00:00Z'],
        'daylight time, the week after it starts' => ['2027-02', '2027-03-15T13:00:00Z'],
    ]);

    test('goes only to whoever has not shared, counting a draft as not shared', function () {
        /** @var TestCase $this */
        [$david, $katie] = participantsJoinedAt('2026-10-20T12:00:00Z');
        Memory::factory()->for($david)->forMonth(CalendarMonth::of(2026, 10))->create();
        Memory::factory()->for($katie)->forMonth(CalendarMonth::of(2026, 10))->shared()->create();

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

    test('is not sent once the next month opens', function () {
        /** @var TestCase $this */
        [$david] = participantsJoinedAt('2026-10-20T12:00:00Z');

        sendEmailsAt('2026-12-01T14:00:00Z');

        Notification::assertNotSentTo($david, Nudge::class);
        expectEmailedAbout($david, MonthOpened::class, '2026-11');
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
        participantsJoinedAt('2026-11-02T12:00:00Z');

        sendEmailsAt('2026-11-15T14:00:00Z');

        Notification::assertNothingSent();
    });

    test('both participants about the first month that opens after they joined', function () {
        /** @var TestCase $this */
        [$david, $katie] = participantsJoinedAt('2026-11-02T12:00:00Z');

        sendEmailsAt('2026-12-01T14:00:00Z');

        expectEmailedAbout($david, MonthOpened::class, '2026-11');
        expectEmailedAbout($katie, MonthOpened::class, '2026-11');
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
        $this->artisan('memories:send-emails')
            ->expectsOutputToContain('Queued 0 emails.')
            ->assertSuccessful();
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

test('says how many emails it queued', function () {
    /** @var TestCase $this */
    participantsJoinedAt('2026-10-20T12:00:00Z');
    $this->travelTo('2026-11-01T14:00:00Z');

    $this->artisan('memories:send-emails')
        ->expectsOutputToContain('Queued 2 emails.')
        ->assertSuccessful();
});
