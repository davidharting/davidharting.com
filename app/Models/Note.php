<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'lead',
        'content',
        'hidden',
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
}
