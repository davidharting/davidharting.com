<?php

use App\Models\Creator;
use Tests\TestCase;

test('factory works', function () {
    $creator = Creator::factory()->create();
    expect($creator)->toBeInstanceOf(Creator::class);
    expect($creator->name)->toContain(' ', 'Creator name should be a full name');
});
