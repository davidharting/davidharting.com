<?php

namespace App\Ai\Tools;

use App\Enum\MediaEventTypeName;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\MediaEventType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateMediaEvent implements Tool
{
    public function description(): Stringable|string
    {
        return <<<'TEXT'
            Log a media tracking event (started, finished, abandoned, or comment) for a
            media item. The occurred_at date must be an absolute ISO 8601 datetime —
            resolve any relative references ("yesterday", "last Saturday") before calling
            this tool.
            TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $mediaId = $request->integer('media_id');
        $media = Media::find($mediaId);

        if ($media === null) {
            return json_encode(
                ['error' => "Media with id {$mediaId} not found."],
                JSON_THROW_ON_ERROR,
            );
        }

        $eventTypeString = (string) $request->string('event_type');
        $eventTypeName = MediaEventTypeName::tryFrom(strtolower($eventTypeString));

        if ($eventTypeName === null) {
            $valid = implode(', ', array_column(MediaEventTypeName::cases(), 'value'));

            return json_encode(
                ['error' => "Invalid event_type \"{$eventTypeString}\". Must be one of: {$valid}."],
                JSON_THROW_ON_ERROR,
            );
        }

        $mediaEventType = MediaEventType::where('name', $eventTypeName)->sole();

        $occurredAt = Carbon::parse((string) $request->string('occurred_at'));
        $comment = ((string) $request->string('comment')) ?: null;

        $event = MediaEvent::create([
            'media_id' => $media->id,
            'media_event_type_id' => $mediaEventType->id,
            'occurred_at' => $occurredAt,
            'comment' => $comment,
        ]);

        return json_encode([
            'event_id' => $event->id,
            'media_id' => $media->id,
            'event_type' => $eventTypeName->value,
            'occurred_at' => $event->occurred_at->toIso8601String(),
        ], JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'media_id' => $schema->integer()->required()
                ->description('The ID of the media item to log the event for.'),
            'event_type' => $schema->string()->required()
                ->enum(array_column(MediaEventTypeName::cases(), 'value'))
                ->description('The type of event to log.'),
            'occurred_at' => $schema->string()->required()
                ->description('The absolute ISO 8601 datetime when the event occurred. Resolve any relative dates before calling this tool.'),
            'comment' => $schema->string()
                ->description('An optional comment for comment-type events or annotations.'),
        ];
    }
}
