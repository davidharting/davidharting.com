<?php

use App\Models\User;
use Database\Seeders\MemorySeeder;
use Tests\TestCase;

test('offers a guest the login link and no Account link', function () {
    /** @var TestCase $this */
    $this->get('/')
        ->assertSee(route('login'))
        ->assertDontSee(route('dashboard'));
});

test('offers anyone signed in an Account link instead of Login', function () {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->create())->get('/')
        ->assertSee(route('dashboard'))
        ->assertDontSee(route('login'));
});

test('gives memories pages a slim nav of Home, Memories and Account', function (string $url) {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);

    $this->actingAs(User::firstWhere('email', 'katie@example.com'))->get($url)
        ->assertSuccessful()
        ->assertSee(route('memories.index'))
        ->assertSee(route('dashboard'))
        ->assertDontSee(route('notes.index'))
        ->assertDontSee(route('media.index'));
})->with([
    'the list' => '/memories',
    'a month' => '/memories/2026-09',
]);
