<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
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


    public function toFeedItem(): FeedItem
    {
        $fullPost = view('components.notes.prose', ['note' => $this])->render();
        $fullPost = preg_replace('/<!--.*?-->/s', '', $fullPost);
        // Problem: I have logic for deleaing with all the cases in html of
        // Lead only? Title only? etc.
        // Need to abstract and share?
        return FeedItem::create()
            ->id($this->slug)
            ->title($this->rssTitle())
            // TODO: Not yet dealing with all full post variants?
            // I also don't want the title in the full post?
            ->summary($fullPost)
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
            return $this->lead;
        }

        return 'Untitled note';
    }
}
