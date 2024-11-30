<?php

namespace App\Actions\GoodreadsImport;

enum Shelf: string
{
    case Backlog = 'to-read';
    case Read = 'read';
    case Abandoned = 'abandoned';
    case Reading = 'currently-reading';
}
