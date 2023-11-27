<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scorecard extends Model
{
    use HasFactory;
    use HasUlids;

    protected $guarded = [];

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }
}
