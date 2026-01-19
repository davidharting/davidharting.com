<?php

use App\Enum\MediaEventTypeName;
use App\Models\Media;
use App\Models\MediaEvent;
use Tests\TestCase;

test('factory', function () {
    $event = MediaEvent::factory()->create();

    expect(MediaEvent::count())->toBe(1);
    expect(Media::count())->toBe(1);

    /** @var Media $media */
    $media = $event->media;

    MediaEvent::factory(3)->create([
        'media_id' => $media->id,
    ]);

    expect(Media::count())->toBe(1);
    expect(MediaEvent::count())->toBe(4);
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

    expect($event->mediaEventType->name)->toBeInstanceOf(MediaEventTypeName::class);
});

test('comment factory method creates comment event with text', function () {
    /** @var TestCase $this */
    $event = MediaEvent::factory()->comment('My thoughts on chapter 5')->create();

    expect($event->mediaEventType->name)->toBe(MediaEventTypeName::COMMENT);
    expect($event->comment)->toBe('My thoughts on chapter 5');
});
