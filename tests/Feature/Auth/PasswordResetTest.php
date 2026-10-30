<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

test('reset password link screen can be rendered', function () {
    /** @var TestCase $this */
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    /** @var TestCase $this */
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    /** @var TestCase $this */
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    /** @var TestCase $this */
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors();

        return true;
    });
});

test('resetting the password verifies an unverified email', function () {
    /** @var TestCase $this */
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        return true;
    });

    $this->assertTrue($user->fresh()->hasVerifiedEmail());
});

test('a failed reset does not verify the email', function () {
    /** @var TestCase $this */
    $user = User::factory()->unverified()->create();

    $this->post('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertFalse($user->fresh()->hasVerifiedEmail());
});

test('a token sent to one address cannot verify another', function () {
    /** @var TestCase $this */
    Notification::fake();

    $recipient = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();

    $this->post('/forgot-password', ['email' => $recipient->email]);

    Notification::assertSentTo($recipient, ResetPassword::class, function ($notification) use ($other) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $other->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        return true;
    });

    $this->assertFalse($other->fresh()->hasVerifiedEmail());
    $this->assertFalse($recipient->fresh()->hasVerifiedEmail());
});

test('reset password screen shows the email without letting it be edited', function () {
    /** @var TestCase $this */
    $response = $this->get('/reset-password/a-token?email=katie@example.test');

    $response->assertOk();
    $response->assertSeeText('Choose a new password for katie@example.test.');
    $response->assertSee('type="hidden" name="email" value="katie@example.test"', escape: false);
    $response->assertDontSee('<label for="email"', escape: false);
});

test('reset password screen shows why a reset failed', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->from('/reset-password/not-a-real-token?email='.urlencode($user->email))
        ->followingRedirects()
        ->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSeeText('This password reset token is invalid.')
        ->assertSeeText('Choose a new password for '.$user->email.'.');
});
