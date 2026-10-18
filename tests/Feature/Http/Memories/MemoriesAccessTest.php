<?php

use App\Models\MemoryParticipant;
use App\Models\User;
use Database\Seeders\MemorySeeder;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->travelTo('2026-10-18T12:00:00Z');
});

test('is not found for anyone who is not a participant', function (string $url, Closure $as) {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);
    $as($this);

    $this->get($url)->assertNotFound();
})->with([
    '/memories',
    '/memories/2026-09',
])->with([
    'a guest' => fn () => fn (TestCase $test) => null,
    'a signed-in user' => fn () => fn (TestCase $test) => $test->actingAs(User::factory()->create()),
    'an admin' => fn () => fn (TestCase $test) => $test->actingAs(User::factory()->create(['is_admin' => true])),
]);

test('is not found, rather than not allowed, for any other method', function (string $method, string $url) {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);

    $this->call($method, $url)->assertNotFound();
})->with([
    ['POST', '/memories'],
    ['DELETE', '/memories/2026-09'],
]);

test('is not found while there is only one participant', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    MemoryParticipant::create(['user_id' => $user->id]);

    $this->actingAs($user)->get('/memories')->assertNotFound();
});

test('links to memories in the nav for participants only', function () {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);

    $this->actingAs(User::firstWhere('email', 'katie@example.com'))->get('/')
        ->assertSee(route('memories.index'));

    $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/')
        ->assertDontSee(route('memories.index'));
});
