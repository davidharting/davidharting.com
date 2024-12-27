<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum MediaTypeName: string implements HasLabel
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

    public function getLabel(): ?string
    {
        return $this->displayName();
    }

    /**
     * @return string[]
     */
    public static function displayNames(): array
    {
        return array_map(fn ($case) => $case->displayName(), self::cases());
    }
}
