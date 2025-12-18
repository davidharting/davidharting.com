<?php

namespace App\Models;

use App\Enum\MediaEventTypeName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaEventType extends Model
{
    use HasFactory;

    /** @var array<string, int|null> */
    private static array $idCache = [];

    protected function casts(): array
    {
        return [
            'name' => MediaEventTypeName::class,
        ];
    }

    /**
     * Get the database ID for a given event type name.
     * Results are cached in memory for the duration of the request.
     */
    public static function idFor(MediaEventTypeName $name): ?int
    {
        return self::$idCache[$name->value] ??= static::where('name', $name)->first()?->id;
    }

    public function mediaEvents(): HasMany
    {
        return $this->hasMany(MediaEvent::class);
    }
}
