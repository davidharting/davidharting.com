<?php

namespace App\Notifications\Memories;

use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a participant that the other person's memory of a month is ready to
 * read, with a link to it. It carries no memory text or photos.
 */
class Revealed extends Notification
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
        $month = $this->month->calendarMonth->name();
        $otherName = MemoryParticipant::otherThan($notifiable)->displayName();

        return (new MailMessage)
            ->subject("{$otherName}'s {$month} memory is ready to read")
            ->greeting("Hi {$notifiable->displayName()},")
            ->line("{$otherName}'s {$month} memory is ready to read.")
            ->action("Read {$otherName}'s memory", route('memories.show', $this->month));
    }
}
