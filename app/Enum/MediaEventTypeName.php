<?php

namespace App\Enum;

enum MediaEventTypeName: string
{
    case STARTED = 'started';
    case FINISHED = 'finished';
    case ABANDONED = 'abandoned';
}
