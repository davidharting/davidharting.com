<?php

use App\Models\Media;
use App\Models\MediaEvent;
use Tests\TestCase;

test('factory', function () {
    /** @var TestCase $this */
    $event = MediaEvent::factory()->create();

    $this->assertEquals(1, MediaEvent::count());
    $this->assertEquals(1, Media::count());

    $media = $event->media;

    MediaEvent::factory(3)->create([
        'media_id' => $media->id,
    ]);

    $this->assertEquals(1, Media::count());
    $this->assertEquals(4, MediaEvent::count());
});

test('from media', function () {
    /** @var TestCase $this */
    Media::factory()->hasEvents(3)->create();
    $this->assertEquals(1, Media::count());
    $this->assertEquals(3, MediaEvent::count());
});

test('casts media event type name to enum', function () {
    /** @var TestCase $this */
    $event = MediaEvent::factory()->create();

    expect($event->mediaEventType->name)->toBeInstanceOf(\App\Enum\MediaEventTypeName::class);
});

test('comment factory method creates comment event with text', function () {
    /** @var TestCase $this */
    $event = MediaEvent::factory()->comment('My thoughts on chapter 5')->create();

    expect($event->mediaEventType->name)->toBe(\App\Enum\MediaEventTypeName::COMMENT);
    expect($event->comment)->toBe('My thoughts on chapter 5');
});
