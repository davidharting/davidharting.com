<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\MemoryPhoto;
use App\Models\Month;
use App\Models\User;
use App\Notifications\Memories\LateShare;
use App\Support\CalendarMonth;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    Storage::fake('private');
    $this->travelTo('2026-12-05T14:00:00Z');
    $this->david = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'David']))->create()->user;
    $this->katie = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'Katie']))->create()->user;
    $this->october = Month::of(CalendarMonth::of(2026, 10));
    Memory::factory()->for($this->katie)->forMonth($this->october->calendarMonth)->shared()->create(['body' => 'We went apple picking.']);
    $davids = Memory::factory()->for($this->david)->forMonth($this->october->calendarMonth)->shared()->create(['body' => 'We carved pumpkins.']);
    $photo = MemoryPhoto::factory()->for($davids)->ready()->captioned('Our pumpkins')->create();
    Storage::disk('private')->put($photo->web_path, 'web copy');
});

function lateShareMailForDavid(): MailMessage
{
    return (new LateShare(test()->october))->toMail(test()->david);
}

test('names the month and links to their memory on the month page', function () {
    /** @var TestCase $this */
    $mail = lateShareMailForDavid();

    expect($mail->subject)->toBe('Your October 2026 memory, to keep')
        ->and($mail->viewData['intro'])->toBe("You shared your October 2026 memory, so Katie can read it now. Here's a copy to keep.")
        ->and($mail->viewData['actionUrl'])->toBe(route('memories.show', [$this->october, 'who' => 'yours']));
});

test('carries only their own memory and photos', function () {
    /** @var TestCase $this */
    $mail = lateShareMailForDavid();
    $rendered = (string) $mail->render();

    expect($rendered)->toContain('Your October 2026 memory', 'We carved pumpkins.', 'Photo 1: Our pumpkins')
        ->and($rendered)->not->toContain('apple picking')
        ->and(collect($mail->rawAttachments)->pluck('name')->all())->toBe(['david-2026-10-1.jpg']);
});
