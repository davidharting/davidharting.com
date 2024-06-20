<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Note extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function html(): string
    {
        return Str::of($this->content)->markdown();
    }
}
