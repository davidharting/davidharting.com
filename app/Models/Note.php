<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'content',
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

    public function renderContent(): string
    {
        if ($this->markdown_content !== null) {
            return Str::markdown($this->markdown_content);
        }

        return $this->content ?? '';
    }

    public function toFeedItem(): FeedItem
    {
        $fullPost = view('components.notes.prose', ['note' => $this])->render();
        $fullPost = preg_replace('/<!--.*?-->/s', '', $fullPost);

        return FeedItem::create()
            ->id($this->slug)
            ->title($this->rssTitle())
            ->summary($this->rssSummary())
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

    /**
     * I'm including full test of the post in the RSS feed
     * rather than just a summary and link
     */
    private function rssSummary(): string
    {
        $fullContent = Str::of('');

        // Do not include title
        // Because that will already be visible as the title of the post in the RSS reader

        if ($this->lead) {
            $fullContent = $fullContent->append('<p><i>');
            $fullContent = $fullContent->append($this->lead);
            $fullContent = $fullContent->append('</i></p>');
        }

        $renderedContent = $this->renderContent();
        if ($renderedContent) {
            $fullContent = $fullContent->append($renderedContent);
        }

        return $fullContent;
    }
}
