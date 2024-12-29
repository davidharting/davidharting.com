<?php

namespace App;

enum MediaList: string
{
    case Logbook = 'logbook';
    case Backlog = 'backlog';
    case InProgress = 'in-progress';
}
