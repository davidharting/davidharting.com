<?php

namespace App\Models;

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
}
