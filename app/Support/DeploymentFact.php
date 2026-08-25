<?php

namespace App\Support;

/**
 * One labelled value identifying the running deployment.
 *
 * Carries its own optional link so presentation code never has to recognise a
 * particular fact by name to decide how to render it.
 */
class DeploymentFact
{
    public function __construct(
        public string $label,
        public string $value,
        public ?string $url = null,
    ) {}
}
