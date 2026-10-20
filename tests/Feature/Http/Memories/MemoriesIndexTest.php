<?php

use App\Models\Memory;
use App\Models\User;
use App\Support\Month;
use Database\Seeders\MemorySeeder;
use Tests\Support\RenderedHead;
use Tests\TestCase;

/**
 * The seeder counts back from the latest open month, September 2026:
 * September is before its reveal, August has David's late share, July
 * revealed when both shared, June is empty, and May revealed at its deadline
 * with David's draft never shared. March is the launch month.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
    $this->actingAs(User::firstWhere('email', 'david@example.com'));
});

test('lists every month since launch, newest first, empty months included', function () {
    /** @var TestCase $this */
    $this->get('/memories')
        ->assertSuccessful()
        ->assertSeeTextInOrder([
            'September 2026',
            'August 2026',
            'July 2026',
            'June 2026',
            'May 2026',
            'April 2026',
            'March 2026',
        ])
        ->assertDontSeeText('February 2026');
});

test('titles the page Memories', function () {
    /** @var TestCase $this */
    $response = $this->get('/memories');

    expect(RenderedHead::from($response)->title)->toBe('Memories - davidharting.com');
});

test('says where both memories stand before the reveal, without either memory\'s text', function () {
    /** @var TestCase $this */
    // September: Katie started her draft on Oct 3; David shared on Oct 5.
    $this->get('/memories')
        ->assertSeeText('Katie started on Oct 3 · Yours: Shared Oct 5')
        ->assertDontSeeText('pumpkin patch')
        ->assertDontSeeText('It snowed for the first time');
});

test('previews the first line of their memory once a month has revealed', function () {
    /** @var TestCase $this */
    // Katie's August memory.
    $this->get('/memories')
        ->assertSeeText('Beach week! Five days with nowhere to be.');
});

test('says nothing was written for an empty month', function () {
    /** @var TestCase $this */
    $this->get('/memories')
        ->assertSeeText('Nothing written.');
});

test('marks their unshared draft after the reveal, without its text, and previews the viewer\'s own memory instead', function () {
    /** @var TestCase $this */
    // May: David's draft was never shared; Katie's "New job!" memory was.
    // 'David: draft' is the title and aria-label of his initial, not visible text.
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

test('truncates a long first line with an ellipsis', function () {
    /** @var TestCase $this */
    Memory::factory()->for(User::firstWhere('email', 'katie@example.com'))->forMonth(Month::of(2026, 6))->shared()->create([
        'body' => str_repeat('word ', 100),
    ]);

    // The preview stops at 160 characters, drops the trailing space, and ends in '...'.
    $this->get('/memories')
        ->assertSeeText(str_repeat('word ', 31).'word...')
        ->assertDontSeeText(str_repeat('word ', 33));
});
