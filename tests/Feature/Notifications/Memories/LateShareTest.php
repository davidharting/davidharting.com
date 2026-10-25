<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\MemoryPhoto;
use App\Models\Month;
use App\Models\User;
use App\Notifications\Memories\LateShare;
use App\Support\CalendarMonth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    Storage::fake('private');
    $this->travelTo('2026-12-05T14:00:00Z');
    $this->david = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'David']))->create()->user;
    $this->katie = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'Katie']))->create()->user;
});

test('sends them a copy of their own memory and links to it on the month page', function () {
    /** @var TestCase $this */
    $october = Month::of(CalendarMonth::of(2026, 10));
    Memory::factory()->for($this->katie)->forMonth($october->calendarMonth)->shared()->create(['body' => 'We went apple picking.']);
    $davids = Memory::factory()->for($this->david)->forMonth($october->calendarMonth)->shared()->create(['body' => 'We carved pumpkins.']);
    $photo = MemoryPhoto::factory()->for($davids)->ready()->captioned('Our pumpkins')->create();
    Storage::disk('private')->put($photo->web_path, 'web copy');

    $mail = (new LateShare($october))->toMail($this->david);
    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('Your October 2026 memory, to keep')
        ->and($mail->viewData['intro'])->toBe("You shared your October 2026 memory, so Katie can read it now. Here's a copy to keep.")
        ->and($mail->viewData['actionUrl'])->toBe(route('memories.show', [$october, 'who' => 'yours']))
        ->and($rendered)->toContain('Your memory', 'We carved pumpkins.', 'david-2026-10-1.jpg: Our pumpkins')
        ->and($rendered)->not->toContain('apple picking')
        ->and(collect($mail->rawAttachments)->pluck('name')->all())->toBe(['david-2026-10-1.jpg']);
});
