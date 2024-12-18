<?php

namespace App\Models;

use App\Enum\MediaEventTypeName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaEventType extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'name' => MediaEventTypeName::class,
        ];
    }

    public function mediaEvents(): HasMany
    {
        return $this->hasMany(MediaEvent::class);
    }
}
