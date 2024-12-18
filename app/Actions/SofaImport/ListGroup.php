<?php

namespace App\Actions\SofaImport;

enum ListGroup: string
{
    case FunLists = 'Fun Lists';
    case Backlogs = 'Backlogs';
    case Tracking = 'Tracking';
    case Main = 'Main';
}
