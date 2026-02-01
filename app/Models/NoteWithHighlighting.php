<?php

namespace App\Models;

use App\Services\MarkdownRenderer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;

/**
 * Example of how Note would look with server-side syntax highlighting.
 *
 * Changes from original Note.php:
 * 1. Added MarkdownRenderer dependency injection or direct instantiation
 * 2. Changed renderContent() to use the custom renderer instead of Str::markdown()
 *
 * This is a PROOF OF CONCEPT - you would modify the original Note.php, not create a new file.
 */
class NoteWithHighlighting extends Model implements Feedable
{
    use HasFactory;

    protected $table = 'notes';

    protected $fillable = [
        'slug',
        'title',
        'lead',
        'markdown_content',
        'visible',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime:Y-m-d H:i:s.uO',
    ];

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function publicationDate(): string
    {
        return $this->published_at->format('Y F j');
    }

    /**
     * Render markdown content with syntax highlighting.
     *
     * Uses MarkdownRenderer service which integrates Tempest Highlight
     * (or Spatie highlighter) with CommonMark for server-side code highlighting.
     */
    public function renderContent(): ?string
    {
        if (! $this->markdown_content) {
            return null;
        }

        // Option 1: Direct instantiation (simple)
        $renderer = new MarkdownRenderer();

        return $renderer->render($this->markdown_content);

        // Option 2: Use Laravel's container (better for testing/DI)
        // return app(MarkdownRenderer::class)->render($this->markdown_content);
    }

    public function toFeedItem(): FeedItem
    {
        return FeedItem::create()
            ->id($this->slug)
            ->title($this->title ?? 'Untitled note')
            ->summary($this->renderContent() ?? '')
            ->updated($this->published_at)
            ->link(route('notes.show', $this->slug))
            ->authorName('David Harting')
            ->authorEmail('connect@davidharting.com');
    }
}
