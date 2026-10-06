<?php

namespace App\Enum;

enum MemoryPhotoStatus: string
{
    case PROCESSING = 'processing';
    case READY = 'ready';
    case FAILED = 'failed';
}
