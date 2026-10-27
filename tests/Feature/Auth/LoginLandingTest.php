<?php

use App\Models\MemoryParticipant;
use App\Models\User;
use Database\Seeders\MemorySeeder;
use Tests\TestCase;

test('lands a participant who is not the admin on their memories', function () {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);

    $this->post('/login', ['email' => 'katie@example.com', 'password' => 'password'])
        ->assertRedirect(route('memories.index', absolute: false));
});

test('lands the admin on the Account page, even as a participant', function () {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);

    $this->post('/login', ['email' => 'david@example.com', 'password' => 'password'])
        ->assertRedirect(route('profile.edit', absolute: false));
});

test('lands someone who is not a participant on the Account page', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('profile.edit', absolute: false));
});

test('lands the only participant on the Account page while memories is not live yet', function () {
    /** @var TestCase $this */
    $participant = User::factory()->create();
    MemoryParticipant::create(['user_id' => $participant->id]);

    $this->post('/login', ['email' => $participant->email, 'password' => 'password'])
        ->assertRedirect(route('profile.edit', absolute: false));
});

test('returns a participant to the page they were sent to log in from', function () {
    /** @var TestCase $this */
    $this->seed(MemorySeeder::class);

    $this->get('/profile')->assertRedirect(route('login'));

    $this->post('/login', ['email' => 'katie@example.com', 'password' => 'password'])
        ->assertRedirect('/profile');
});

describe('Remember me', function () {
    test('is ticked when the login page first loads', function () {
        /** @var TestCase $this */
        $this->assertMatchesRegularExpression('/name="remember"\s+checked/', $this->get('/login')->getContent());
    });

    test('stays unticked after a failed login where it was unticked', function () {
        /** @var TestCase $this */
        $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);

        $this->assertMatchesRegularExpression('/name="remember"\s+class=/', $this->get('/login')->getContent());
    });
});
