<?php

use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use App\Notifications\Memories\MonthOpened;
use App\Support\CalendarMonth;
use Tests\TestCase;

test('says the month is open and links to their editor for it', function () {
    /** @var TestCase $this */
    $this->travelTo('2026-11-01T14:00:00Z');
    $david = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'David']))->create()->user;
    $october = Month::of(CalendarMonth::of(2026, 10));

    $mail = (new MonthOpened($october))->toMail($david);

    expect($mail->subject)->toBe('October is open for memories')
        ->and($mail->greeting)->toBe('Hi David,')
        ->and($mail->actionText)->toBe('Write your October memory')
        ->and($mail->actionUrl)->toBe(route('memories.write', $october));
});
