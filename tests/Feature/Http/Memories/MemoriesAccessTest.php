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
]);

test('is not found for anyone who is not a participant', function (string $url, Closure $as) {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);
    $as($this);

    $this->get($url)->assertNotFound();
})->with('memories urls')->with([
    // Pest resolves the outer fn lazily; the inner one runs inside the test to sign in as that visitor.
    'a guest' => fn () => fn (TestCase $test) => null,
    'a signed-in user' => fn () => fn (TestCase $test) => $test->actingAs(User::factory()->create()),
    'an admin' => fn () => fn (TestCase $test) => $test->actingAs(User::factory()->create(['is_admin' => true])),
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
