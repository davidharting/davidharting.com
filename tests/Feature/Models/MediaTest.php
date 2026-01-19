<?php

use App\Models\Media;

test('factory', function () {
    $media = Media::factory()->create();
    expect($media)->toBeInstanceOf(Media::class);
    expect($media->creator)->not->toBeNull();
    expect($media->creator?->name)->not->toBeNull();

    $media = Media::factory()->create(['creator_id' => null]);
    expect($media->creator)->toBeNull();
});
