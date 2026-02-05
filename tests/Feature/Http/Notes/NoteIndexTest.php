<?php

use App\Models\Note;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

test('renders an empty list', function () {
    /** @var TestCase $this */
    $response = $this->get('/notes');
    $response->assertSuccessful();
    $response->assertSeeText('No notes yet');
});

test('displays visible notes with most recently published first', function () {
    /** @var TestCase $this */
    Note::factory()->createMany([
        ['title' => 'oldest note', 'published_at' => Carbon::create(2000, 01, 01), 'visible' => true],
        ['lead' => 'middle note', 'published_at' => Carbon::create(2008, 05, 07), 'visible' => true],
        ['title' => 'newest note', 'published_at' => Carbon::create(2020, 07, 10), 'visible' => true],
        ['title' => 'SHOULD NOT SEE', 'visible' => false],
    ]);

    expect(Note::all()->count())->toBe(4);
    expect(Note::where('visible', true)->count())->toBe(3);

    $response = $this->get('/notes');
    $response->assertSuccessful();
    $response->assertDontSeeText('SHOULD NOT SEE');
    $response->assertSeeTextInOrder([
        'newest note',
        'middle note',
        'oldest note',
    ]);
});

test('admin can see unpublished notes in index', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    Note::factory()->createMany([
        ['title' => 'Published note', 'visible' => true, 'published_at' => Carbon::create(2020, 1, 1)],
        ['title' => 'Unpublished note', 'visible' => false, 'published_at' => Carbon::create(2021, 1, 1)],
    ]);

    $response = $this->actingAs($admin)->get('/notes');
    $response->assertSuccessful();
    $response->assertSeeText('Published note');
    $response->assertSeeText('Unpublished note');
});

test('unpublished notes show Unpublished badge for admin', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    Note::factory()->create(['title' => 'Draft note', 'visible' => false]);

    $response = $this->actingAs($admin)->get('/notes');
    $response->assertSuccessful();
    $response->assertSeeText('Draft note');
    $response->assertSeeText('Unpublished');
});

test('published notes do not show Unpublished badge', function () {
    /** @var TestCase $this */
    $admin = User::factory()->create(['is_admin' => true]);

    Note::factory()->create(['title' => 'Live note', 'visible' => true]);

    $response = $this->actingAs($admin)->get('/notes');
    $response->assertSuccessful();
    $response->assertSeeText('Live note');
    $response->assertDontSeeText('Unpublished');
});

test('non-admin user cannot see unpublished notes', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['is_admin' => false]);

    Note::factory()->createMany([
        ['title' => 'Published note', 'visible' => true, 'published_at' => Carbon::create(2020, 1, 1)],
        ['title' => 'Unpublished note', 'visible' => false, 'published_at' => Carbon::create(2021, 1, 1)],
    ]);

    $response = $this->actingAs($user)->get('/notes');
    $response->assertSuccessful();
    $response->assertSeeText('Published note');
    $response->assertDontSeeText('Unpublished note');
});

test('guest user cannot see unpublished notes', function () {
    /** @var TestCase $this */
    Note::factory()->createMany([
        ['title' => 'Published note', 'visible' => true, 'published_at' => Carbon::create(2020, 1, 1)],
        ['title' => 'Unpublished note', 'visible' => false, 'published_at' => Carbon::create(2021, 1, 1)],
    ]);

    $response = $this->get('/notes');
    $response->assertSuccessful();
    $response->assertSeeText('Published note');
    $response->assertDontSeeText('Unpublished note');
});

test('displays year headings and abbreviated dates in correct order', function () {
    /** @var TestCase $this */
    Note::factory()->createMany([
        // 2024 - two notes in same year
        ['title' => 'Winter update', 'published_at' => Carbon::create(2024, 12, 15), 'visible' => true],
        ['title' => 'Spring thoughts', 'published_at' => Carbon::create(2024, 3, 8), 'visible' => true],
        // 2022 - gap year (2023 missing)
        ['title' => 'Mid-year note', 'published_at' => Carbon::create(2022, 7, 22), 'visible' => true],
        // 2020 - another gap (2021 missing)
        ['title' => 'Old reflection', 'published_at' => Carbon::create(2020, 1, 5), 'visible' => true],
    ]);

    $response = $this->get('/notes');
    $response->assertSuccessful();

    // Verify year headings, dates, and titles appear in correct order
    $response->assertSeeTextInOrder([
        '2024',
        'Dec 15',
        'Winter update',
        'Mar 8',
        'Spring thoughts',
        '2022',
        'Jul 22',
        'Mid-year note',
        '2020',
        'Jan 5',
        'Old reflection',
    ]);

    // Verify gap years are not present
    $response->assertDontSeeText('2023');
    $response->assertDontSeeText('2021');
});
