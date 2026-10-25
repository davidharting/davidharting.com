<?php

namespace App\Notifications\Memories;

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\Month;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a participant that a month has revealed and carries its memories, so
 * their inbox keeps a copy of the month.
 */
class Revealed extends Notification
{
    /**
     * @param  DateTimeInterface  $sharedBy  When the email was claimed. Only memories shared by then go in, so one shared afterwards goes out as its own late share.
     */
    public function __construct(public Month $month, public DateTimeInterface $sharedBy) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    /**
     * The other person's memory first, then theirs if they had shared it.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $label = $this->month->calendarMonth->label();
        $otherName = MemoryParticipant::otherThan($notifiable)->displayName();
        $copies = $this->month->memories()->with(['user', 'photos'])->get()
            ->filter(fn (Memory $memory): bool => $memory->isShared() && $memory->shared_at->lessThanOrEqualTo($this->sharedBy))
            ->sortBy(fn (Memory $memory): bool => $memory->user->is($notifiable))
            ->map(fn (Memory $memory): MemoryCopy => MemoryCopy::of($memory, $notifiable))
            ->values();

        $mail = (new MailMessage)
            ->subject("{$label} has revealed")
            ->markdown('mail.memories.copies', [
                'greeting' => "Hi {$notifiable->displayName()},",
                'intro' => $copies->count() === 2
                    ? "You've both shared, so here are both of your {$label} memories to read and keep."
                    : "{$otherName}'s {$label} memory is ready to read. Here it is to keep.",
                'copies' => $copies,
                'actionText' => 'Read it on the site',
                'actionUrl' => route('memories.show', $this->month),
            ]);

        foreach ($copies->flatMap(fn (MemoryCopy $copy): array => $copy->attachments()) as $attachment) {
            $mail->attach($attachment);
        }

        return $mail;
    }
}
