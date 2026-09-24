<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Creator extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Match a creator by name, case-insensitively, so "frank herbert" finds
     * "Frank Herbert".
     *
     * @param  Builder<Creator>  $query
     */
    #[Scope]
    protected function named(Builder $query, string $name): void
    {
        $query->whereRaw('lower(name) = lower(?)', [$name]);
    }
}
