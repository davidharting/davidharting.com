<?php

use App\Models\Media;

test('factory', function () {
    $media = Media::factory()->create();
    $this->assertNotNull($media);
    $this->assertNotNull($media->creator->name);

    $media = Media::factory()->create(['creator_id' => null]);
    $this->assertNull($media->creator);
});
