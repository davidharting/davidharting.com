<?php

use App\Models\Month;
use App\Models\User;
use App\Notifications\Memories\MonthOpened;
use App\Support\CalendarMonth;
use Tests\TestCase;

test('says it is time to write about the month and links to their editor for it', function () {
    /** @var TestCase $this */
    $david = User::factory()->create(['display_name' => 'David']);
    $october = Month::of(CalendarMonth::of(2026, 10));

    $mail = (new MonthOpened($october))->toMail($david);

    expect($mail->subject)->toBe('Time to write your October memory')
        ->and($mail->greeting)->toBe('Hi David,')
        ->and($mail->actionText)->toBe('Write your October memory')
        ->and($mail->actionUrl)->toBe(route('memories.write', $october));
});
