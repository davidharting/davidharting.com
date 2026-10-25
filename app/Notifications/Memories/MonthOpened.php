<?php

namespace App\Notifications\Memories;

use App\Models\Month;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a participant that a month is over and open for their memory.
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
            ->subject("{$name} is open for memories")
            ->greeting("Hi {$notifiable->displayName()},")
            ->line("{$name} is over, so you can write your memory of it now.")
            ->line('It stays yours alone until you share it.')
            ->action("Write your {$name} memory", route('memories.write', $this->month));
    }
}
