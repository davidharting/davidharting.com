<?php

use App\Models\User;
use Database\Seeders\MemorySeeder;
use Tests\TestCase;

test('offers a guest the login link and no Account link', function () {
    /** @var TestCase $this */
    $this->get('/')
        ->assertSee(route('login'))
        ->assertDontSee(route('profile.edit'));
});

test('offers anyone signed in an Account link instead of Login', function () {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->create())->get('/')
        ->assertSee(route('profile.edit'))
        ->assertDontSee(route('login'));
});

test('gives memories pages a slim nav of Home, Memories and Account', function (string $url) {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
    $this->seed(MemorySeeder::class);

    $this->actingAs(User::firstWhere('email', 'katie@example.com'))->get($url)
        ->assertSuccessful()
        ->assertSee('href="'.route('home').'"', false)
        ->assertSee('href="'.route('memories.index').'"', false)
        ->assertSee('href="'.route('profile.edit').'"', false)
        ->assertDontSee(route('notes.index'))
        ->assertDontSee(route('media.index'))
        ->assertDontSee(route('pages.index'));
})->with([
    'the list' => '/memories',
    'a month' => '/memories/2026-09',
]);
