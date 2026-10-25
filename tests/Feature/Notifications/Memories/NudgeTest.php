<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use App\Notifications\Memories\Nudge;
use App\Support\CalendarMonth;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

beforeEach(function () {
    $this->travelTo('2026-11-15T14:00:00Z');
    $this->david = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'David']))->create()->user;
    $this->katie = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'Katie']))->create()->user;
});

function octoberNudgeFor(User $user): MailMessage
{
    return (new Nudge(Month::of(CalendarMonth::of(2026, 10))))->toMail($user);
}

test('links to their editor for the month', function () {
    /** @var TestCase $this */
    $mail = octoberNudgeFor($this->david);

    expect($mail->subject)->toBe('A nudge about your October memory')
        ->and($mail->greeting)->toBe('Hi David,')
        ->and($mail->actionUrl)->toBe(route('memories.write', Month::of(CalendarMonth::of(2026, 10))));
});

test('says when the month reveals at the latest', function () {
    /** @var TestCase $this */
    $mail = octoberNudgeFor($this->david);

    expect($mail->outroLines)->toBe(["October reveals once you've both shared, or on Dec 1."]);
});

describe('where they stand', function () {
    test('asks them to start when they have not', function () {
        /** @var TestCase $this */
        $mail = octoberNudgeFor($this->david);

        expect($mail->introLines[1])->toBe("You haven't started yours yet.")
            ->and($mail->actionText)->toBe('Write yours');
    });

    test('asks them to keep writing a draft', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->david)->forMonth(CalendarMonth::of(2026, 10))->create();

        $mail = octoberNudgeFor($this->david);

        expect($mail->introLines[1])->toBe('Yours is still a draft.')
            ->and($mail->actionText)->toBe('Keep writing');
    });
});

describe('where the other person stands', function () {
    test('says when they have not started', function () {
        /** @var TestCase $this */
        expect(octoberNudgeFor($this->david)->introLines[0])->toBe("Katie hasn't started yet.");
    });

    test('says when they started a draft', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->katie)->forMonth(CalendarMonth::of(2026, 10))->create(['created_at' => '2026-11-02T15:00:00Z']);

        expect(octoberNudgeFor($this->david)->introLines[0])->toBe("Katie started on Nov 2 and hasn't shared yet.");
    });

    test('says when they have shared', function () {
        /** @var TestCase $this */
        Memory::factory()->for($this->katie)->forMonth(CalendarMonth::of(2026, 10))->shared()->create();

        expect(octoberNudgeFor($this->david)->introLines[0])->toBe('Katie has shared.');
    });
});

test('carries no memory text', function () {
    /** @var TestCase $this */
    Memory::factory()->for($this->katie)->forMonth(CalendarMonth::of(2026, 10))->shared()->create(['body' => 'We went apple picking.']);

    $rendered = (string) octoberNudgeFor($this->david)->render();

    expect($rendered)->not->toContain('apple picking');
});
