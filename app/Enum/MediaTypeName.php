<?php

namespace App\Enum;

enum MediaTypeName: string
{
    case Album = 'album';
    case Book = 'book';
    case Movie = 'movie';
    case TvShow = 'tv show';
    case VideoGame = 'video game';

    /**
     * Get the display value for the enum case.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::Album => 'Album',
            self::Book => 'Book',
            self::Movie => 'Movie',
            self::TvShow => 'TV Show',
            self::VideoGame => 'Video Game',
        };
    }

    /**
     * @return string[]
     */
    public static function displayNames(): array
    {
        return array_map(fn ($case) => $case->displayName(), self::cases());
    }
}
