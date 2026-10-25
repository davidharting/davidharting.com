<?php

namespace App\Notifications\Memories;

use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Models\User;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One memory as it goes into an email, so the email can stand in for the
 * site if the site's data is ever lost: its text exactly as written, and the
 * web copy of each photo as an attachment, listed with its caption.
 */
final readonly class MemoryCopy
{
    /**
     * @param  Collection<int, MemoryPhoto>  $photos  The photos to attach, in the author's order.
     * @param  int  $photosLeftOut  Photos with nothing to attach yet, or that didn't fit in the email.
     */
    private function __construct(
        public string $heading,
        public string $body,
        public string $authorSlug,
        public string $yearMonth,
        public Collection $photos,
        public int $photosLeftOut,
    ) {}

    /**
     * Attaches the memory's ready photos in order while they fit in the
     * attachment budget. Photos still processing or failed have nothing to
     * attach, and photos past the budget are left out so the email stays
     * under the mail provider's size limit; both count as left out.
     *
     * @param  int  $attachmentBudget  Bytes of photos this copy may attach.
     */
    public static function of(Memory $memory, User $viewer, int $attachmentBudget): self
    {
        $author = $memory->user;
        $label = $memory->month->calendarMonth->label();
        $attached = new Collection;
        $attachedBytes = 0;

        foreach ($memory->photos as $photo) {
            if (! $photo->isReady() || $attachedBytes + $photo->web()->bytes > $attachmentBudget) {
                continue;
            }

            $attached->push($photo);
            $attachedBytes += $photo->web()->bytes;
        }

        return new self(
            heading: $author->is($viewer) ? "Your {$label} memory" : "{$author->displayName()}'s {$label} memory",
            body: trim($memory->body),
            authorSlug: Str::slug($author->displayName()),
            yearMonth: $memory->month->calendarMonth->toYearMonth(),
            photos: $attached,
            photosLeftOut: $memory->photos->count() - $attached->count(),
        );
    }

    /**
     * The body as Markdown that renders exactly as written: no character in
     * it formats anything, and each line break the author typed is kept.
     */
    public function markdownBody(): string
    {
        return collect(preg_split('/\R/', $this->body))
            ->map(fn (string $line): string => self::escapeMarkdown($line))
            ->implode("  \n");
    }

    /**
     * The line for a photo in the list under the memory, for example
     * "Photo 2: The orchard".
     */
    public function photoLine(MemoryPhoto $photo): string
    {
        $line = 'Photo '.$this->number($photo);

        return $photo->caption === null ? $line : $line.': '.self::escapeMarkdown($photo->caption);
    }

    /**
     * The file name a photo is attached under, for example "katie-2026-09-1.jpg".
     */
    public function filename(MemoryPhoto $photo): string
    {
        $extension = pathinfo($photo->web()->path, PATHINFO_EXTENSION);

        return "{$this->authorSlug}-{$this->yearMonth}-{$this->number($photo)}.{$extension}";
    }

    public function attachedBytes(): int
    {
        return $this->photos->sum(fn (MemoryPhoto $photo): int => $photo->web()->bytes);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return $this->photos
            ->map(fn (MemoryPhoto $photo): Attachment => Attachment::fromStorageDisk('private', $photo->web()->path)
                ->as($this->filename($photo))
                ->withMime($photo->web()->mimeType))
            ->all();
    }

    private function number(MemoryPhoto $photo): int
    {
        return $this->photos->search(fn (MemoryPhoto $attached): bool => $attached->is($photo)) + 1;
    }

    /**
     * Backslash the characters that would start Markdown formatting, so the
     * text renders as plain text. HTML is already escaped by Blade. Leading
     * spaces are dropped, since four of them would make a code block.
     */
    private static function escapeMarkdown(string $line): string
    {
        $line = preg_replace('/[\\\\`*_\[\]~|]/', '\\\\$0', ltrim($line));
        $line = preg_replace('/^[#>+\-=]/', '\\\\$0', $line);

        return preg_replace('/^(\d+)([.)])/', '$1\\\\$2', $line);
    }
}
