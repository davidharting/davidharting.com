<?php

namespace App\Support;

/**
 * One piece of metadata describing the running deployment.
 */
class DeploymentFact
{
    public function __construct(
        public string $label,
        public string $value,
        public ?string $url = null,
    ) {}
}
