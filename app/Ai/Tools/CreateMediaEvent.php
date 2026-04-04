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
            media item. occurred_at accepts any date string Carbon can parse — ISO 8601,
            natural language ("yesterday", "last Saturday"), or a plain date ("2026-03-15").
            If no specific time was mentioned, default to noon (12:00:00) of the relevant day.
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
                ->description('When the event occurred. Accepts ISO 8601, plain dates, or natural language Carbon can parse. If no time was mentioned, use noon (12:00:00) of the relevant day, e.g. "2026-03-15T12:00:00".'),
            'comment' => $schema->string()
                ->description('An optional free-text note to attach to this event. Can be used with any event type (started, finished, abandoned, or comment). When logging a started or finished event with a remark, pass the remark here — do NOT create a separate comment event.'),
        ];
    }
}
