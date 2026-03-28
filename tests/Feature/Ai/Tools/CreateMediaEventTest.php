<?php

use App\Ai\Tools\CreateMediaEvent;
use App\Models\Media;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

test('CreateMediaEvent creates event with occurred_at set to provided datetime', function () {
    /** @var TestCase $this */
    $media = Media::factory()->book()->create();

    $result = json_decode(
        (new CreateMediaEvent)->handle(new Request([
            'media_id' => $media->id,
            'event_type' => 'finished',
            'occurred_at' => '2026-03-15T10:00:00Z',
        ])),
        true,
    );

    $this->assertArrayHasKey('event_id', $result);
    $this->assertSame($media->id, $result['media_id']);
    $this->assertSame('finished', $result['event_type']);

    // occurred_at must be the provided value, not now()
    $this->assertDatabaseHas('media_events', [
        'media_id' => $media->id,
    ]);

    $event = \App\Models\MediaEvent::find($result['event_id']);
    $this->assertTrue($event->occurred_at->isSameDay('2026-03-15'));
});

test('CreateMediaEvent creates event with comment', function () {
    /** @var TestCase $this */
    $media = Media::factory()->book()->create();

    $result = json_decode(
        (new CreateMediaEvent)->handle(new Request([
            'media_id' => $media->id,
            'event_type' => 'comment',
            'occurred_at' => '2026-03-15T10:00:00Z',
            'comment' => 'Really enjoyed this.',
        ])),
        true,
    );

    $this->assertArrayHasKey('event_id', $result);
    $this->assertDatabaseHas('media_events', [
        'media_id' => $media->id,
        'comment' => 'Really enjoyed this.',
    ]);
});

test('CreateMediaEvent returns error when media_id not found', function () {
    /** @var TestCase $this */
    $result = json_decode(
        (new CreateMediaEvent)->handle(new Request([
            'media_id' => 99999,
            'event_type' => 'finished',
            'occurred_at' => '2026-03-15T10:00:00Z',
        ])),
        true,
    );

    $this->assertArrayHasKey('error', $result);
    $this->assertStringContainsString('99999', $result['error']);
});

test('CreateMediaEvent sole() throws if MediaEventType is missing', function () {
    /** @var TestCase $this */
    $media = Media::factory()->book()->create();

    // Delete the event type to simulate missing seed data
    \App\Models\MediaEventType::where('name', 'started')->delete();

    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    (new CreateMediaEvent)->handle(new Request([
        'media_id' => $media->id,
        'event_type' => 'started',
        'occurred_at' => '2026-03-15T10:00:00Z',
    ]));
});

test('CreateMediaEvent schema defines required fields', function () {
    /** @var TestCase $this */
    $fields = (new CreateMediaEvent)->schema(new JsonSchemaTypeFactory);

    $this->assertArrayHasKey('media_id', $fields);
    $this->assertArrayHasKey('event_type', $fields);
    $this->assertArrayHasKey('occurred_at', $fields);
    $this->assertArrayHasKey('comment', $fields);
});

test('CreateMediaEvent schema enumerates valid event_type values', function () {
    /** @var TestCase $this */
    $schema = (new CreateMediaEvent)->schema(new JsonSchemaTypeFactory);
    $compiled = $schema['event_type']->toArray();

    $this->assertEqualsCanonicalizing(
        ['started', 'finished', 'abandoned', 'comment'],
        $compiled['enum'],
    );
});
