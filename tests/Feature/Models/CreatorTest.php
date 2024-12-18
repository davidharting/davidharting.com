<?php

use App\Models\Creator;
use Tests\TestCase;

test('factory works', function () {
    /** @var TestCase $this */
    $creator = Creator::factory()->create();
    $this->assertNotNull($creator);
    $this->assertStringContainsString(' ', $creator->name, 'Creator name should be a full name');

});
