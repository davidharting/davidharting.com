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

test('is not found for a month outside the list or a malformed month', function (string $month) {
    /** @var TestCase $this */
    $this->actingAs($this->katie)->get("/memories/{$month}/write")->assertNotFound();
})->with([
    'not open yet' => '2026-10',
    // The seeder's launch month is March 2026.
    'before launch' => '2026-02',
    'not a month' => '2026-13',
]);
