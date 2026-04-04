<?php

use App\Ai\Tools\CreateMediaEvent;
use App\Enum\MediaEventTypeName;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\MediaEventType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

describe('handle()', function () {
    test('creates event with occurred_at set to provided datetime', function () {
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
        $event = MediaEvent::find($result['event_id']);
        $this->assertTrue($event->occurred_at->isSameDay('2026-03-15'));
    });

    test('creates event with comment', function () {
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

    test('attaches comment to a non-comment event without creating a separate event', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create();

        $result = json_decode(
            (new CreateMediaEvent)->handle(new Request([
                'media_id' => $media->id,
                'event_type' => 'started',
                'occurred_at' => '2026-03-15T12:00:00Z',
                'comment' => 'Really excited for this one.',
            ])),
            true,
        );

        $this->assertArrayHasKey('event_id', $result);
        $this->assertSame('started', $result['event_type']);
        $this->assertDatabaseHas('media_events', [
            'media_id' => $media->id,
            'comment' => 'Really excited for this one.',
        ]);
        $this->assertSame(1, MediaEvent::where('media_id', $media->id)->count());
    });

    test('returns error when media_id not found', function () {
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

    test('sole() throws if MediaEventType is missing', function () {
        /** @var TestCase $this */
        $media = Media::factory()->book()->create();

        // Delete the event type to simulate missing seed data
        MediaEventType::where('name', MediaEventTypeName::STARTED)->delete();

        $this->expectException(ModelNotFoundException::class);

        (new CreateMediaEvent)->handle(new Request([
            'media_id' => $media->id,
            'event_type' => 'started',
            'occurred_at' => '2026-03-15T10:00:00Z',
        ]));
    });
});

describe('schema()', function () {
    test('defines required fields', function () {
        /** @var TestCase $this */
        $fields = (new CreateMediaEvent)->schema(new JsonSchemaTypeFactory);

        $this->assertArrayHasKey('media_id', $fields);
        $this->assertArrayHasKey('event_type', $fields);
        $this->assertArrayHasKey('occurred_at', $fields);
        $this->assertArrayHasKey('comment', $fields);
    });

    test('enumerates valid event_type values', function () {
        /** @var TestCase $this */
        $schema = (new CreateMediaEvent)->schema(new JsonSchemaTypeFactory);
        $compiled = $schema['event_type']->toArray();

        $this->assertEqualsCanonicalizing(
            ['started', 'finished', 'abandoned', 'comment'],
            $compiled['enum'],
        );
    });
});
