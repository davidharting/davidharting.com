<?php

namespace App\Mcp\Tools\Admin;

use App\Enum\MediaEventTypeName;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\MediaEventType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Log a tracking event against a media item, registered only on AdminServer.
 *
 * Unlike App\Ai\Tools\CreateMediaEvent, this takes a calendar date and no
 * time, and no natural-language dates: "last Saturday" would be resolved
 * against the server's clock rather than the conversation's, so the agent
 * resolves it and passes a date. Every event is stored at noon UTC.
 *
 * Deliberately not idempotent: a second call logs a second event. It does not
 * dedupe, but returns the item's other events of the same type so the agent
 * can notice it has logged a second "finished".
 */
#[IsDestructive(false)]
#[Description(<<<'TEXT'
    Record that David started, finished or abandoned a media item, or add a
    dated comment to it. The item must already be in the library: find its
    media_id with query-media, or add it with create-media first.

    An item's status is derived from its events: its current status is the
    latest started, finished or abandoned event, its started date the earliest
    started event, and its finished date the latest finished event. Comment
    events never change the status. Logging an event is therefore the only way
    to change an item's status, and a wrong one shows on the public website, so
    make sure of the item and the date first.

    Every call logs a new event, even one identical to an existing event. The
    response lists the item's other events of the same type: if there are any,
    tell David, since a second "finished" is often a mistake — though a re-read
    or re-watch is a legitimate reason for one.

    To say something about the event itself — what David thought of a book he
    just finished, say — pass it as this event's comment rather than logging a
    separate comment event.
    TEXT)]
class CreateMediaEvent extends Tool
{
    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found": both resolve through the same filtered collection.
     *
     * handle() asks the narrower questions of whether the caller may log
     * events, and read the existing events this tool returns.
     */
    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->can('administrate') ?? false;
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if ($user?->cannot('create', MediaEvent::class) ?? true) {
            return Response::error('You are not authorized to log media events.');
        }

        // The response includes the item's other events and their comments.
        if ($user->cannot('viewAny', MediaEvent::class)) {
            return Response::error('You are not authorized to read media events.');
        }

        $validated = $request->validate([
            'media_id' => ['required', 'integer', Rule::exists(Media::class, 'id')],
            'event_type' => ['required', 'string', Rule::enum(MediaEventTypeName::class)],
            'occurred_on' => ['required', 'string', 'date_format:Y-m-d'],
            'comment' => ['sometimes', 'string', 'filled'],
        ], [
            'occurred_on.date_format' => 'The occurred on field must be a date in the form YYYY-MM-DD, such as 2026-03-15.',
        ]);

        $media = Media::findOrFail($validated['media_id']);
        $eventTypeName = MediaEventTypeName::from($validated['event_type']);
        $eventType = MediaEventType::where('name', $eventTypeName)->sole();

        $event = MediaEvent::create([
            'media_id' => $media->id,
            'media_event_type_id' => $eventType->id,
            'occurred_at' => $this->occurredAt($validated['occurred_on']),
            'comment' => $validated['comment'] ?? null,
        ]);

        $otherEventsOfType = $media->events()
            ->whereBelongsTo($eventType)
            ->whereKeyNot($event->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn (MediaEvent $otherEvent): array => [
                'event_id' => $otherEvent->id,
                'occurred_at' => $otherEvent->occurred_at->toIso8601String(),
                'comment' => $otherEvent->comment,
            ])
            ->all();

        return Response::structured([
            'event_id' => $event->id,
            'media_id' => $media->id,
            'title' => $media->title,
            'event_type' => $eventTypeName->value,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'comment' => $event->comment,
            'other_events_of_type' => $otherEventsOfType,
        ]);
    }

    /**
     * Noon UTC on the given date: it keeps the calendar date the same in any
     * timezone within twelve hours of UTC.
     */
    private function occurredAt(string $occurredOn): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $occurredOn, 'UTC')->setTime(12, 0);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'media_id' => $schema->integer()
                ->required()
                ->description('The id of the media item, as returned by query-media or create-media.'),
            'event_type' => $schema->string()
                ->required()
                ->enum(array_column(MediaEventTypeName::cases(), 'value'))
                ->description('started, finished or abandoned to change the item\'s status; comment to add a dated comment without changing it.'),
            'occurred_on' => $schema->string()
                ->required()
                ->description('The date it happened, as YYYY-MM-DD (2026-03-15). No time: every event is stored at noon UTC on that date. Resolve relative dates such as "yesterday" yourself; they are refused.'),
            'comment' => $schema->string()
                ->description('David\'s private comment on this event. Not shown on the public website.'),
        ];
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, JsonSchema>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The id of the event just logged.'),
            'media_id' => $schema->integer()->description('The id of the media item.'),
            'title' => $schema->string()->description('The item\'s title as stored.'),
            'event_type' => $schema->string()->description('One of: started, finished, abandoned, comment.'),
            'occurred_at' => $schema->string()->description('When the event happened as stored (ISO 8601).'),
            'comment' => $schema->string()->nullable()->description('The event\'s comment as stored. Private.'),
            'other_events_of_type' => $schema->array()
                ->items($schema->object([
                    'event_id' => $schema->integer()->description('The id of the event.'),
                    'occurred_at' => $schema->string()->description('When the event happened (ISO 8601).'),
                    'comment' => $schema->string()->nullable()->description('The event\'s comment. Private.'),
                ]))
                ->description('The item\'s other events of the same type, oldest first, not including the one just logged. Empty when this is the first.'),
        ];
    }
}
