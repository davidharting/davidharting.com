<?php

use App\Models\MemoryParticipant;
use App\Models\User;
use Tests\Support\RenderedHead;
use Tests\TestCase;

test('profile page is displayed', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile page is titled account and offers log out', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile')
        ->assertSeeHtml('>Account</h1>')
        ->assertSee(route('logout'));

    $this->assertSame('Account - davidharting.com', RenderedHead::from($response)->title);
});

test('profile page gives the admin the admin tools', function () {
    /** @var TestCase $this */
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/profile')
        ->assertSeeTextInOrder(['Admin', 'Filament Admin', 'Kitchen Sink'])
        ->assertDontSeeText('Backup database');
});

test('profile page does not give anyone else the admin tools', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')
        ->assertDontSeeText('Filament Admin');
});

test('profile page offers to delete the account', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')
        ->assertSeeText('Delete Your Account');
});

test('profile page does not offer a memories participant to delete their account', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    MemoryParticipant::create(['user_id' => $user->id]);

    $this->actingAs($user)->get('/profile')
        ->assertOk()
        ->assertDontSeeText('Delete Your Account');
});

test('profile information can be updated', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
