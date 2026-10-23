<?php

use App\Models\User;
use App\Notifications\Welcome;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

test('creates an unverified, non-admin account', function () {
    /** @var TestCase $this */
    Notification::fake();

    $this->artisan('users:add', ['email' => 'katie@example.test', 'name' => 'Katie Harting'])
        ->expectsOutputToContain('Created an account for katie@example.test')
        ->assertSuccessful();

    $katie = User::where('email', 'katie@example.test')->sole();

    expect($katie->name)->toBe('Katie Harting')
        ->and($katie->is_admin)->toBeFalse()
        ->and($katie->hasVerifiedEmail())->toBeFalse();
});

test('emails them a welcome with a link to choose a password, and nothing else', function () {
    /** @var TestCase $this */
    Notification::fake();

    $this->artisan('users:add', ['email' => 'katie@example.test', 'name' => 'Katie Harting'])
        ->assertSuccessful();

    $katie = User::where('email', 'katie@example.test')->sole();

    Notification::assertSentTo($katie, Welcome::class);
    Notification::assertCount(1);
});

test('the welcome link lets them choose a password, verifies their email, and lets them log in', function () {
    /** @var TestCase $this */
    Notification::fake();

    $this->artisan('users:add', ['email' => 'katie@example.test', 'name' => 'Katie Harting'])
        ->assertSuccessful();

    $katie = User::where('email', 'katie@example.test')->sole();

    Notification::assertSentTo($katie, Welcome::class, function (Welcome $notification) {
        /** @var TestCase $this */
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => 'katie@example.test',
            'password' => 'a-password-katie-chose',
            'password_confirmation' => 'a-password-katie-chose',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect($katie->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->post('/login', ['email' => 'katie@example.test', 'password' => 'a-password-katie-chose']);

    $this->assertAuthenticatedAs($katie);
});

test('refuses an email that already has an account, and sends nothing', function () {
    /** @var TestCase $this */
    Notification::fake();
    User::factory()->create(['email' => 'katie@example.test', 'name' => 'Katie Harting']);

    $this->artisan('users:add', ['email' => 'katie@example.test', 'name' => 'Someone Else'])
        ->expectsOutputToContain('There is already a user with the email [katie@example.test].')
        ->assertFailed();

    expect(User::sole()->name)->toBe('Katie Harting');

    Notification::assertNothingSent();
});

test('refuses something that is not an email address', function () {
    /** @var TestCase $this */
    Notification::fake();

    $this->artisan('users:add', ['email' => 'katie', 'name' => 'Katie Harting'])
        ->expectsOutputToContain('[katie] is not a valid email address.')
        ->assertFailed();

    expect(User::count())->toBe(0);

    Notification::assertNothingSent();
});
