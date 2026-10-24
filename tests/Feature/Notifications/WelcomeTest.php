<?php

use App\Models\User;
use App\Notifications\Welcome;
use Tests\TestCase;

test('invites them to choose a password rather than reset one', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $mail = (new Welcome('a-token'))->toMail($user);

    expect($mail->subject)->toBe('Welcome to '.config('app.name'))
        ->and($mail->actionText)->toBe('Choose your password')
        ->and(implode(' ', $mail->introLines))->not->toContain('reset');
});

test('links to the password reset page with their token', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $mail = (new Welcome('a-token'))->toMail($user);

    expect($mail->actionUrl)->toBe(route('password.reset', ['token' => 'a-token', 'email' => $user->email]));
});

test('says how long the link lasts and what to do if it lapses', function () {
    /** @var TestCase $this */
    config(['auth.passwords.users.expire' => 90]);
    $user = User::factory()->create();

    $mail = (new Welcome('a-token'))->toMail($user);

    expect(implode(' ', $mail->outroLines))
        ->toContain('expires in 90 minutes')
        ->toContain('Forgot your password?');
});
