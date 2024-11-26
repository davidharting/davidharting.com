<?php

use App\Models\Media;

test('factory', function () {
    $media = Media::factory()->create();
    $this->assertNotNull($media);
});

test('factory with 1 creator', function () {
    $media = Media::factory()->hasCreators(1)->create();
    $this->assertCount(1, $media->creators);
});

test('factory wtih many creators', function () {
    $media = Media::factory()->hasCreators(10)->create();
    $this->assertCount(10, $media->creators);
});
