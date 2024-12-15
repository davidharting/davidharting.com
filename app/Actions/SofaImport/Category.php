<?php

namespace App\Actions\SofaImport;

enum Category: string
{
    case Album = 'Music Album';
    case Book = 'Book';
    case Show = 'TV Show';
    case VideoGame = 'Video Game';
    case Movie = 'Movie';
}
