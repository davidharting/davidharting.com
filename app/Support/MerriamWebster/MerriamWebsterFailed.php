<?php

namespace App\Support\MerriamWebster;

use RuntimeException;

/**
 * Merriam-Webster didn't answer a lookup with dictionary JSON: the API key is
 * missing or rejected, the request failed, or the body wasn't JSON.
 */
class MerriamWebsterFailed extends RuntimeException {}
