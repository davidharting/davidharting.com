<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Media extends Model
{
    use HasFactory;

    protected $table = 'medias'; // TODO: I think media is already plural 🤦‍♂️

    // TODO: Rename description to private_note or similar
    protected $fillable = ['year', 'title', 'description'];

    public function mediaType(): BelongsTo
    {
        return $this->belongsTo(MediaType::class);
    }

    public function creators(): BelongsToMany
    {
        return $this->belongsToMany(Creator::class);
    }
}
