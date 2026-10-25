<?php

namespace App\Notifications\Memories;

use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends a participant a copy of the memory they shared after their reveal
 * email, so their inbox keeps the whole month.
 */
class LateShare extends Notification
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
        $label = $this->month->calendarMonth->label();
        $otherName = MemoryParticipant::otherThan($notifiable)->displayName();
        $memory = $this->month->memories()->with(['user', 'photos'])->where('user_id', $notifiable->id)->firstOrFail();
        $copy = MemoryCopy::of($memory, $notifiable);

        $mail = (new MailMessage)
            ->subject("Your {$label} memory, to keep")
            ->markdown('mail.memories.copies', [
                'greeting' => "Hi {$notifiable->displayName()},",
                'intro' => "You shared your {$label} memory, so {$otherName} can read it now. Here's a copy to keep.",
                'copies' => collect([$copy]),
                'actionText' => 'See it on the site',
                'actionUrl' => route('memories.show', [$this->month, 'who' => 'yours']),
            ]);

        foreach ($copy->attachments() as $attachment) {
            $mail->attach($attachment);
        }

        return $mail;
    }
}
