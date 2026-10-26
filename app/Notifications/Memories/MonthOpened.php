<?php

namespace App\Notifications\Memories;

use App\Models\Month;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a participant that a month is over and it's time to write their memory of it.
 */
class MonthOpened extends Notification
{
    public function __construct(public Month $month) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $name = $this->month->calendarMonth->name();

        return (new MailMessage)
            ->subject("Time to write your {$name} memory")
            ->greeting("Hi {$notifiable->displayName()},")
            ->line("{$name} is over, so it's time to write up your memory of it.")
            ->action("Write your {$name} memory", route('memories.write', $this->month));
    }
}
