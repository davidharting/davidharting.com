<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * PROTOTYPE (#251): a bare memory, so both photo variants have something to attach to.
 */
class Memory extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    public const MAX_PHOTOS = 10;

    protected $fillable = ['user_id', 'month', 'body'];

    protected $casts = [
        'month' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Variant A: hand-rolled photos, in display order.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(MemoryPhoto::class)->orderBy('position');
    }

    /**
     * Variant B: Media Library's 'photos' collection.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'])
            // Query, not getMedia(): the loaded relation goes stale while adding. Unlike variant A, nothing locks against a parallel upload.
            ->acceptsFile(fn (File $file, Memory $memory): bool => $memory->media()->where('collection_name', 'photos')->count() < self::MAX_PHOTOS);
    }

    /**
     * Variant B: under the cli engine the generator already made each copy, so the driver only re-saves it.
     */
    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        foreach (['web' => [2048, 82], 'thumb' => [512, 80]] as $name => [$maxEdge, $quality]) {
            $conversion = $this->addMediaConversion($name)->performOnCollections('photos')->nonOptimized();

            if (config('media-library.prototype_engine') !== 'cli') {
                $conversion->fit(Fit::Max, $maxEdge, $maxEdge)->quality($quality);
            }
        }
    }
}
