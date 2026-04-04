<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Feed\NoteFeedItem;
use Illuminate\Support\Str;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;

class Note extends Model implements Feedable
{
    use HasFactory;

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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            $post->slug = $post->generateSlug();
        });
    }

    private function generateSlug(): string
    {
        if ($this->slug) {
            return $this->slug;
        }
        if ($this->title) {
            return Str::slug($this->title);
        }

        return Str::lower(Str::ulid($this->published_at));
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function publicationDate(): string
    {
        return $this->published_at->format('Y F j');
    }

    public function renderContent(): ?string
    {
        if (! $this->markdown_content) {
            return null;
        }

        return Str::markdown($this->markdown_content, [
            'html_input' => 'allow',
            'allow_unsafe_links' => true,
            'disallowed_raw_html' => [
                // Allow iframes and scripts for embeds (YouTube, GitHub Gist, Bluesky, etc.)
                // Default list minus 'iframe' and 'script': title, textarea, style, xmp, noembed, noframes, plaintext
                'disallowed_tags' => ['title', 'textarea', 'style', 'xmp', 'noembed', 'noframes', 'plaintext'],
            ],
        ]);
    }

    public function toFeedItem(): FeedItem
    {
        return NoteFeedItem::create()
            ->id($this->slug)
            ->title($this->rssTitle())
            ->summary($this->lead ?? '')
            ->content($this->rssContent())
            ->updated($this->published_at)
            ->link(route('notes.show', $this->slug))
            ->authorName('David Harting')
            ->authorEmail('connect@davidharting.com');
    }

    private function rssTitle(): string
    {
        if ($this->title) {
            return $this->title;
        }

        if ($this->lead) {
            // Should I truncate this?
            return $this->lead;
        }

        return 'Untitled note';
    }

    private function rssContent(): string
    {
        return $this->renderContent() ?? '';
    }
}
