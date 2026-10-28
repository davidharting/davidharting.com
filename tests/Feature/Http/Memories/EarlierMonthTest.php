<?php

use App\Models\User;
use Database\Seeders\MemorySeeder;
use Tests\Support\RenderedHead;
use Tests\TestCase;

/**
 * The seeder's launch month is March 2026, so the picker offers February
 * 2026 back to January 2016.
 */
beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
    $this->actingAs(User::firstWhere('email', 'katie@example.com'));
});

test('offers only the months from the month before launch back to January 2016, newest first', function () {
    /** @var TestCase $this */
    $this->get('/memories/earlier')
        ->assertSuccessful()
        ->assertSeeText('Choose a month to write about')
        ->assertSeeTextInOrder(['February 2026', 'January 2026', 'December 2025', 'January 2016'])
        ->assertDontSeeText('March 2026')
        ->assertDontSeeText('December 2015')
        ->assertSee('<button type="submit"', false);
});

test('makes each choice the month\'s editor', function () {
    /** @var TestCase $this */
    $this->get('/memories/earlier')
        ->assertSee('value="'.route('memories.write', '2026-02').'"', false)
        ->assertSee('value="'.route('memories.write', '2016-01').'"', false);
});

test('titles the page Choose a month', function () {
    /** @var TestCase $this */
    $response = $this->get('/memories/earlier');

    expect(RenderedHead::from($response)->title)->toBe('Choose a month - davidharting.com');
});

test('links back to the list', function () {
    /** @var TestCase $this */
    $this->get('/memories/earlier')
        ->assertSee('href="'.route('memories.index').'"', false);
});
