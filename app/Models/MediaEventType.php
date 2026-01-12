<?php

namespace App\Models;

use App\Enum\MediaEventTypeName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
