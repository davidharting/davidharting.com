<?php

namespace App\Memories;

use RuntimeException;

/**
 * A write was refused because the memory changed since the editor loaded it,
 * most likely in a tab on another device.
 */
class MemoryChangedElsewhere extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The memory changed since it was loaded.');
    }
}
