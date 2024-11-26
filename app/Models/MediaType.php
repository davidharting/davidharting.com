<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function medias(): HasMany
    {
        return $this->hasMany(Media::class);
    }
}
