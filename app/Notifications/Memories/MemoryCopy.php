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
 * site if the site's data is ever lost: its text, and the web copy of each
 * ready photo as an attachment with its caption.
 */
final readonly class MemoryCopy
{
    /**
     * @param  Collection<int, MemoryPhoto>  $photos  The photos to attach, in the author's order.
     * @param  int  $photosLeftOut  Photos still processing or failed, so there is nothing to attach.
     */
    private function __construct(
        public string $heading,
        public string $body,
        public string $authorSlug,
        public string $yearMonth,
        public Collection $photos,
        public int $photosLeftOut,
    ) {}

    public static function of(Memory $memory, User $viewer): self
    {
        $author = $memory->user;
        $ready = $memory->photos->filter(fn (MemoryPhoto $photo): bool => $photo->isReady())->values();

        return new self(
            heading: $author->is($viewer) ? 'Your memory' : "{$author->displayName()}'s memory",
            body: trim($memory->body),
            authorSlug: Str::slug($author->displayName()),
            yearMonth: $memory->month->calendarMonth->toYearMonth(),
            photos: $ready,
            photosLeftOut: $memory->photos->count() - $ready->count(),
        );
    }

    /**
     * The body as Markdown, keeping each line break the author typed. Blank
     * lines already separate paragraphs; a single newline would otherwise
     * run two lines together.
     */
    public function markdownBody(): string
    {
        return preg_replace('/(?<!\n)\n(?!\n)/', "  \n", str_replace("\r\n", "\n", $this->body));
    }

    /**
     * The file name a photo is attached under, for example "katie-2026-09-1.jpg".
     */
    public function filename(MemoryPhoto $photo): string
    {
        $number = $this->photos->search(fn (MemoryPhoto $attached): bool => $attached->is($photo)) + 1;
        $extension = pathinfo($photo->web()->path, PATHINFO_EXTENSION);

        return "{$this->authorSlug}-{$this->yearMonth}-{$number}.{$extension}";
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
}
