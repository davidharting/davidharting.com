<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Notes\NotesIndexPage;
use App\Models\Note;
use Carbon\Carbon;
use Livewire\Livewire;

test('renders an empty list', function () {
    Livewire::test(NotesIndexPage::class)->assertSee('No notes yet');
});

test('displays visible notes with most recently updated first', function () {
    Note::factory()->createMany(
        [
            ['content' => 'oldest note', 'updated_at' => Carbon::create(2000, 01, 01), 'visible' => true],
            ['content' => 'middle note', 'updated_at' => Carbon::create(2008, 05, 07), 'visible' => true],
            ['content' => 'newest note', 'updated_at' => Carbon::create(2020, 07, 10), 'visible' => true],
            ['content' => 'SHOULD NOT SEE', 'visible' => true],
        ]
    );

    expect(Note::all()->count())->toBe(4);
    Livewire::test(NotesIndexPage::class)->assertSeeInOrder([
        'newest note', 'middle note', 'oldest note',
    ]);

});
