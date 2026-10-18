<?php

use App\Models\User;
use Database\Seeders\MemorySeeder;
use Tests\Support\RenderedHead;
use Tests\TestCase;

/**
 * The seeder counts back from the latest open month, September 2026.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
    $this->actingAs(User::firstWhere('email', 'david@example.com'));
});

test('lists every month since launch, newest first, empty months included', function () {
    /** @var TestCase $this */
    $response = $this->get('/memories')->assertSuccessful();

    $response->assertSeeTextInOrder([
        'September 2026',
        'August 2026',
        'July 2026',
        'June 2026',
        'May 2026',
        'April 2026',
        'March 2026',
    ]);
    $response->assertDontSeeText('February 2026');
    expect(RenderedHead::from($response)->title)->toBe('Memories - davidharting.com');
});

test('says where both memories stand before the reveal, without their text', function () {
    /** @var TestCase $this */
    $this->get('/memories')
        ->assertSeeText('Katie started on Oct 3 · Yours: Shared Oct 5')
        ->assertDontSeeText('pumpkin patch')
        ->assertDontSeeText('It snowed for the first time');
});

test('opens a revealed month with a line of their memory', function () {
    /** @var TestCase $this */
    $this->get('/memories')
        ->assertSeeText('Beach week! Five days with nowhere to be.')
        ->assertSeeText('Nothing written.');
});

test('marks their unshared draft after the reveal, without its text', function () {
    /** @var TestCase $this */
    $this->actingAs(User::firstWhere('email', 'katie@example.com'))
        ->get('/memories')
        ->assertSee('David: draft')
        ->assertDontSeeText('Spring cleaning')
        ->assertSeeText('New job!');
});

test('links each month to its page', function () {
    /** @var TestCase $this */
    $this->get('/memories')
        ->assertSee(route('memories.show', '2026-09'))
        ->assertSee(route('memories.show', '2026-03'));
});
