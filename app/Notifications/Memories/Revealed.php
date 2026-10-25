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
     * The most photo bytes one email attaches, so that with base64 encoding
     * it stays well under MailerSend's 25 MB message limit.
     */
    public const int ATTACHMENT_BUDGET = 15_000_000;

    /**
     * @param  DateTimeInterface  $claimedAt  When the email was claimed. Only memories shared by then go in, so one shared afterwards goes out as its own late share.
     */
    public function __construct(public Month $month, public DateTimeInterface $claimedAt) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    /**
     * The other person's memory first, then theirs if they had shared it.
     * Photos attach in that order until the attachment budget runs out.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $label = $this->month->calendarMonth->label();
        $otherName = MemoryParticipant::otherThan($notifiable)->displayName();
        $memories = $this->month->memories()->with(['user', 'photos'])->get()
            ->filter(fn (Memory $memory): bool => $memory->wasSharedBy($this->claimedAt))
            ->sortBy(fn (Memory $memory): bool => $memory->user->is($notifiable));

        $budget = self::ATTACHMENT_BUDGET;
        $copies = $memories->map(function (Memory $memory) use ($notifiable, &$budget): MemoryCopy {
            $copy = MemoryCopy::of($memory, $notifiable, $budget);
            $budget -= $copy->attachedBytes();

            return $copy;
        })->values();
        $both = $copies->count() === 2;

        return (new MailMessage)
            ->subject($both ? "Your {$label} memories" : "{$otherName}'s {$label} memory")
            ->markdown('mail.memories.copies', [
                'greeting' => "Hi {$notifiable->displayName()},",
                'intro' => $both
                    ? "You've both shared, so here are both of your {$label} memories to read and keep."
                    : "{$otherName}'s {$label} memory is ready to read. Here's a copy to keep.",
                'copies' => $copies,
                'actionText' => $both ? 'Read them on the site' : 'Read it on the site',
                'actionUrl' => route('memories.show', $this->month),
            ])
            ->attachMany($copies->flatMap(fn (MemoryCopy $copy): array => $copy->attachments())->all());
    }
}
