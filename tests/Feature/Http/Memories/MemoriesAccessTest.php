<?php

use App\Models\MemoryParticipant;
use App\Models\User;
use Database\Seeders\MemorySeeder;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
});

dataset('memories urls', [
    '/memories',
    '/memories/2026-09',
    '/memories/2026-09/write',
    '/memories/earlier',
]);

test('sends a guest to log in, and back to the page afterwards', function (string $url) {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);

    $this->get($url)->assertRedirect(route('login'));

    $this->post('/login', ['email' => 'katie@example.com', 'password' => 'password'])
        ->assertRedirect($url);
})->with('memories urls');

test('is not found for a signed-in user who is not a participant', function (string $url, bool $isAdmin) {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);

    $this->actingAs(User::factory()->create(['is_admin' => $isAdmin]))
        ->get($url)
        ->assertNotFound();
})->with('memories urls')->with([
    'an ordinary user' => false,
    'the admin' => true,
]);

test('is not found for a participant while they are the only one', function (string $url) {
    /** @var TestCase $this */
    $user = User::factory()->create();
    MemoryParticipant::create(['user_id' => $user->id]);

    $this->actingAs($user)->get($url)->assertNotFound();
})->with('memories urls');

test('links to memories in the nav for participants only', function () {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);
    $adminNonParticipant = User::factory()->create(['is_admin' => true]);

    $this->actingAs(User::firstWhere('email', 'katie@example.com'))->get('/')
        ->assertSee(route('memories.index'));

    $this->actingAs($adminNonParticipant)->get('/')
        ->assertDontSee(route('memories.index'));
});
