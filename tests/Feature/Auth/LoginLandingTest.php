<?php

use App\Models\MemoryParticipant;
use App\Models\User;
use Database\Seeders\MemorySeeder;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

function logIn(TestCase $test, User $user): TestResponse
{
    return $test->post('/login', ['email' => $user->email, 'password' => 'password']);
}

test('lands a participant who is not the admin on their memories', function () {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);

    logIn($this, User::firstWhere('email', 'katie@example.com'))
        ->assertRedirect(route('memories.index', absolute: false));
});

test('lands the admin on the Account page, even as a participant', function () {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);

    logIn($this, User::firstWhere('email', 'david@example.com'))
        ->assertRedirect(route('dashboard', absolute: false));
});

test('lands someone who is not a participant on the Account page', function () {
    /** @var TestCase $this */
    logIn($this, User::factory()->create())
        ->assertRedirect(route('dashboard', absolute: false));
});

test('lands the only participant on the Account page while memories is not live yet', function () {
    /** @var TestCase $this */
    $katie = User::factory()->create();
    MemoryParticipant::create(['user_id' => $katie->id]);

    logIn($this, $katie)->assertRedirect(route('dashboard', absolute: false));
});

test('returns a participant to the page they were sent to log in from', function () {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);

    $this->get('/profile')->assertRedirect(route('login'));

    logIn($this, User::firstWhere('email', 'katie@example.com'))
        ->assertRedirect('/profile');
});

test('ticks Remember me by default', function () {
    /** @var TestCase $this */
    $this->assertMatchesRegularExpression(
        '/name="remember"\s+checked/',
        $this->get('/login')->getContent(),
    );
});
