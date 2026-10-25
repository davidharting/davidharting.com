<?php

namespace App\Notifications\Memories;

use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use App\Support\CalendarMonth;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Nudges a participant who hasn't shared their memory for a month, saying
 * where the other person stands.
 */
class Nudge extends Notification
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
        $hasStarted = $this->month->memoryOf($notifiable) !== null;

        return (new MailMessage)
            ->subject("A nudge about your {$name} memory")
            ->greeting("Hi {$notifiable->displayName()},")
            ->line($this->otherStandingLabel($notifiable))
            ->line($hasStarted ? 'Yours is still a draft.' : "You haven't started yours yet.")
            ->action($hasStarted ? 'Keep writing' : 'Write yours', route('memories.write', $this->month))
            ->line("{$name} reveals once you've both shared, or on ".CalendarMonth::dayLabel($this->month->revealDeadline()).'.');
    }

    /**
     * Where the other person's memory stands, for example "Katie has shared hers."
     */
    protected function otherStandingLabel(User $notifiable): string
    {
        $other = MemoryParticipant::otherThan($notifiable);
        $otherMemory = $this->month->memoryOf($other);
        $otherName = $other->displayName();

        if ($otherMemory === null) {
            return "{$otherName} hasn't started yet.";
        }

        if ($otherMemory->isDraft()) {
            return "{$otherName} started on ".CalendarMonth::dayLabel($otherMemory->created_at)." and hasn't shared yet.";
        }

        return "{$otherName} has shared.";
    }
}
