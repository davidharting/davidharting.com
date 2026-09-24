<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Media extends Model
{
    use HasFactory;

    protected $fillable = ['year', 'title', 'note', 'media_type_id', 'creator_id', 'created_at', 'updated_at'];

    // TODO: Add enum and cast for MediaType

    public function mediaType(): BelongsTo
    {
        return $this->belongsTo(MediaType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MediaEvent::class);
    }

    /**
     * Match media by its identity: title, media type and creator. The title is
     * compared case-insensitively, so "dune" finds "Dune". A null creator
     * matches only media that has no creator.
     *
     * The year is deliberately not part of the identity: it is optional, and
     * an item added without one must still match the same item with one.
     *
     * @param  Builder<Media>  $query
     */
    #[Scope]
    protected function identifiedBy(Builder $query, string $title, int $mediaTypeId, ?int $creatorId): void
    {
        $query->whereRaw('lower(title) = lower(?)', [$title])
            ->where('media_type_id', $mediaTypeId)
            ->where('creator_id', $creatorId);
    }
}
