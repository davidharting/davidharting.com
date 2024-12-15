<?php

namespace App\Actions\SofaImport;

use App\Models\MediaType;

enum Category: string
{
    case Album = 'Music Album';
    case Book = 'Book';
    case Show = 'TV Show';
    case VideoGame = 'Video Game';
    case Movie = 'Movie';

    public function getMediaType(): MediaType
    {
        return match ($this) {
            self::Album => MediaType::where('name', 'album')->first(),
            self::Book => MediaType::where('name', 'book')->first(),
            self::Show => MediaType::where('name', 'tv show')->first(),
            self::VideoGame => MediaType::where('name', 'video game')->first(),
            self::Movie => MediaType::where('name', 'movie')->first(),
        };
    }
}
