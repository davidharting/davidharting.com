<?php

use App\Models\Note;
use App\Models\User;
use Carbon\Carbon;

test('renders an empty list', function () {
    $response = $this->get('/notes');

    $response->assertSee('No notes yet');
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

    $response = $this->get('/notes');

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

    $response = $this->actingAs($admin)->get('/notes');

    $response->assertSeeText('Published note');
    $response->assertSeeText('Unpublished note');
});

test('unpublished notes show Unpublished badge for admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Note::factory()->create(['title' => 'Draft note', 'visible' => false]);

    $response = $this->actingAs($admin)->get('/notes');

    $response->assertSeeText('Draft note');
    $response->assertSeeText('Unpublished');
});

test('published notes do not show Unpublished badge', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Note::factory()->create(['title' => 'Live note', 'visible' => true]);

    $response = $this->actingAs($admin)->get('/notes');

    $response->assertSeeText('Live note');
    $response->assertDontSeeText('Unpublished');
});

test('non-admin user cannot see unpublished notes', function () {
    $user = User::factory()->create(['is_admin' => false]);

    Note::factory()->createMany([
        ['title' => 'Published note', 'visible' => true, 'published_at' => Carbon::create(2020, 1, 1)],
        ['title' => 'Unpublished note', 'visible' => false, 'published_at' => Carbon::create(2021, 1, 1)],
    ]);

    $response = $this->actingAs($user)->get('/notes');

    $response->assertSeeText('Published note');
    $response->assertDontSeeText('Unpublished note');
});

test('guest user cannot see unpublished notes', function () {
    Note::factory()->createMany([
        ['title' => 'Published note', 'visible' => true, 'published_at' => Carbon::create(2020, 1, 1)],
        ['title' => 'Unpublished note', 'visible' => false, 'published_at' => Carbon::create(2021, 1, 1)],
    ]);

    $response = $this->get('/notes');

    $response->assertSeeText('Published note');
    $response->assertDontSeeText('Unpublished note');
});
