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
            ['title' => 'oldest note', 'published_at' => Carbon::create(2000, 01, 01), 'visible' => true],
            ['lead' => 'middle note', 'published_at' => Carbon::create(2008, 05, 07), 'visible' => true],
            ['title' => 'newest note', 'published_at' => Carbon::create(2020, 07, 10), 'visible' => true],
            ['title' => 'SHOULD NOT SEE', 'visible' => false],
        ]
    );

    expect(Note::all()->count())->toBe(4);

    expect(Note::where('visible', true)->count())->toBe(3);

    $response = Livewire::test(NotesIndexPage::class);
    $response->assertDontSee('SHOULD NOT SEE');
    $response->assertSeeInOrder([
        'newest note', 'middle note', 'oldest note',
    ]);
});
