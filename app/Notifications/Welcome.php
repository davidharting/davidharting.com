<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Invites someone whose account was created for them to choose a password.
 *
 * It is a password reset under the hood: the link carries a password broker
 * token and lands on the usual reset page, so it expires like any reset link
 * and a lapsed one is replaced through the forgot password page. Setting the
 * password from it also verifies their email (see NewPasswordController), so
 * this is the only email a new account needs.
 */
class Welcome extends ResetPassword
{
    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to '.config('app.name'))
            ->line('An account has been created for you on '.config('app.name').'.')
            ->line('Choose a password to start using it.')
            ->action('Choose your password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes. If it lapses, use "Forgot your password?" on the login page to get a new one.');
    }
}
