<?php

namespace App\Support\Vips;

use RuntimeException;

/**
 * A libvips command line tool exited with an error, usually because the
 * input isn't an image it can read.
 */
class VipsFailed extends RuntimeException {}
