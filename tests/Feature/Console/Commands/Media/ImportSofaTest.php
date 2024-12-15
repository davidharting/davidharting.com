<?php

use Tests\TestCase;

test('dry run by default', function () {
    /** @var TestCase $this */
    $this->artisan('media:import-sofa')
        ->expectsOutputToContain('Starting dry run')
        ->assertExitCode(0);
});
