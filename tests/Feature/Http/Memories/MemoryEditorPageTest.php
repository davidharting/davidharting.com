<?php

use App\Models\User;
use Database\Seeders\MemorySeeder;
use Tests\Support\RenderedHead;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);
    $this->katie = User::firstWhere('email', 'katie@example.com');
});

test('opens the viewer\'s editor for the month', function () {
    /** @var TestCase $this */
    $response = $this->actingAs($this->katie)->get('/memories/2026-09/write')
        ->assertSuccessful()
        ->assertSeeLivewire('memories.editor')
        ->assertSee(route('memories.show', ['month' => '2026-09', 'who' => 'yours']));

    expect(RenderedHead::from($response)->title)->toBe('Your September 2026 - davidharting.com');
});

test('opens for any month back to January 2016, even one before launch that isn\'t listed', function () {
    /** @var TestCase $this */
    // The seeder's launch month is March 2026.
    $this->actingAs($this->katie)->get('/memories/2016-01/write')
        ->assertSuccessful()
        ->assertSeeLivewire('memories.editor');
});

test('is not found for a month not open yet, before 2016, or malformed', function (string $month) {
    /** @var TestCase $this */
    $this->actingAs($this->katie)->get("/memories/{$month}/write")->assertNotFound();
})->with([
    'not open yet' => '2026-10',
    'before 2016' => '2015-12',
    'not a month' => '2026-13',
]);
