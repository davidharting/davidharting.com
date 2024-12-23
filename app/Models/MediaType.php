<?php

namespace App\Models;

use App\Enum\MediaTypeName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected $casts = [
        'name' => MediaTypeName::class,
    ];

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }
}
