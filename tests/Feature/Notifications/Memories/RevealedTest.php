<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use App\Notifications\Memories\Revealed;
use App\Support\CalendarMonth;
use Tests\TestCase;

test('says the other person\'s memory is ready to read and links to the month page', function () {
    /** @var TestCase $this */
    $david = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'David']))->create()->user;
    MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'Katie']))->create();
    $october = Month::of(CalendarMonth::of(2026, 10));

    $mail = (new Revealed($october))->toMail($david);

    expect($mail->subject)->toBe("Katie's October memory is ready to read")
        ->and($mail->greeting)->toBe('Hi David,')
        ->and($mail->introLines)->toBe(["Katie's October memory is ready to read."])
        ->and($mail->actionText)->toBe("Read Katie's memory")
        ->and($mail->actionUrl)->toBe(route('memories.show', $october));
});

test('carries no memory text', function () {
    /** @var TestCase $this */
    $david = MemoryParticipant::factory()->create()->user;
    $katie = MemoryParticipant::factory()->create()->user;
    $october = CalendarMonth::of(2026, 10);
    Memory::factory()->for($katie)->forMonth($october)->shared()->create(['body' => 'We went apple picking.']);

    $rendered = (string) (new Revealed(Month::of($october)))->toMail($david)->render();

    expect($rendered)->toContain('ready to read')
        ->and($rendered)->not->toContain('apple picking');
});
