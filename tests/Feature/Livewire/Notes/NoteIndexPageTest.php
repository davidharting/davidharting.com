<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Notes\NotesIndexPage;
use App\Models\Note;
use App\Models\User;
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
        'newest note',
        'middle note',
        'oldest note',
    ]);
});

test('admin can see unpublished notes in index', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Note::factory()->createMany([
        ['title' => 'Published note', 'visible' => true, 'published_at' => Carbon::create(2020, 1, 1)],
        ['title' => 'Unpublished note', 'visible' => false, 'published_at' => Carbon::create(2021, 1, 1)],
    ]);

    Livewire::actingAs($admin)
        ->test(NotesIndexPage::class)
        ->assertSeeText('Published note')
        ->assertSeeText('Unpublished note');
});

test('unpublished notes show Unpublished badge for admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Note::factory()->create(['title' => 'Draft note', 'visible' => false]);

    Livewire::actingAs($admin)
        ->test(NotesIndexPage::class)
        ->assertSeeText('Draft note')
        ->assertSeeText('Unpublished');
});

test('published notes do not show Unpublished badge', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Note::factory()->create(['title' => 'Live note', 'visible' => true]);

    Livewire::actingAs($admin)
        ->test(NotesIndexPage::class)
        ->assertSeeText('Live note')
        ->assertDontSeeText('Unpublished');
});

test('non-admin user cannot see unpublished notes', function () {
    $user = User::factory()->create(['is_admin' => false]);

    Note::factory()->createMany([
        ['title' => 'Published note', 'visible' => true, 'published_at' => Carbon::create(2020, 1, 1)],
        ['title' => 'Unpublished note', 'visible' => false, 'published_at' => Carbon::create(2021, 1, 1)],
    ]);

    Livewire::actingAs($user)
        ->test(NotesIndexPage::class)
        ->assertSeeText('Published note')
        ->assertDontSeeText('Unpublished note');
});

test('guest user cannot see unpublished notes', function () {
    Note::factory()->createMany([
        ['title' => 'Published note', 'visible' => true, 'published_at' => Carbon::create(2020, 1, 1)],
        ['title' => 'Unpublished note', 'visible' => false, 'published_at' => Carbon::create(2021, 1, 1)],
    ]);

    Livewire::test(NotesIndexPage::class)
        ->assertSeeText('Published note')
        ->assertDontSeeText('Unpublished note');
});
