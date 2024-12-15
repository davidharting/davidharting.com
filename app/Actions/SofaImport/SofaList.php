<?php

namespace App\Actions\SofaImport;

enum SofaList: string
{
    case WallArt = 'Albums I Want As Wall Art';
    case Books = 'Books 📚';
    case ComedySpecials = 'Comedy Specials';
    case DidNotFinish = 'Did Not Finish';
    case Seasonal = 'Enjoy With The Season';
    case Gaming = 'Gaming 🎮';
    case InProgress = 'In Progress';
    case Movies = 'Movies 🍿';
    case Music = 'Music 🎵';
    case Shows = 'Shows 📺';
    case Logbook = 'Activity';
}
