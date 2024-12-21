<?php

use App\Models\MediaEventType;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

test('factory fails due to unique constraint on name', function () {
    /** @var TestCase $this */
    MediaEventType::factory()->create();
})->throws(UniqueConstraintViolationException::class);
