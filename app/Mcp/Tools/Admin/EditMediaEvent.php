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
 * Correct a tracking event already logged, registered only on AdminServer.
 *
 * The repair counterpart to create-media-event. An item's status is derived
 * from its events — the earliest started, the latest finished, the latest
 * status event — so a wrong event is not outvoted by logging another; it has
 * to be corrected where it is.
 *
 * Omitting a field always leaves it untouched, and clearing the comment is an
 * explicit empty replace, as in edit-media. Dates are calendar dates stored at
 * noon UTC, as in create-media-event.
 *
 * media_id is the move: the only repair for an event logged against the wrong
 * item, and one that loses nothing. The response names the item it came from
 * so the agent reports the move. There is deliberately no way to delete an
 * event here, and the description forbids editing one into something harmless
 * instead: David removes a spurious event himself.
 *
 * Not annotated idempotent, because append_to_comment accumulates.
 */
#[IsDestructive]
#[Description(<<<'TEXT'
    Correct a tracking event already logged against a media item in David
    Harting's library: the wrong event type, the wrong date, a comment to fix,
    or an event logged against the wrong item. Find the event's event_id with
    query-media, requesting history in additional_fields.

    An item's status is derived from its events: its current status is the
    latest started, finished or abandoned event, its started date the earliest
    started event, and its finished date the latest finished event. Logging
    another event does not undo a wrong one — a mistaken start year stays the
    earliest start — so correct the wrong event itself with this tool. A wrong
    event shows on the public website, so make sure of the change first.

    Use this to fix an event, not to hide one. If an event should not exist at
    all — a duplicate, or one David never meant to log — tell David rather than
    editing it into something harmless, such as turning it into a comment. He
    removes it himself.

    Only the fields you pass are changed; any field you leave out is kept
    exactly as it is. To clear the comment pass replace_comment as an empty
    string. Repeating an edit changes nothing further, except
    append_to_comment, which adds its text again.

    To move an event logged against the wrong item, pass the right item's
    media_id. Nothing else about the event changes unless you pass it too. The
    response names the item it was moved from: always tell David about a move.

    The comment is replaced with replace_comment or added to with
    append_to_comment, which adds your text on a new line after what is there.
    Prefer append_to_comment unless David asked to rewrite the comment. The
    response returns the whole comment as it now reads.
    TEXT)]
class EditMediaEvent extends Tool
{
    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found": both resolve through the same filtered collection.
     *
     * handle() asks the narrower questions of whether the caller may edit this
     * event, and read the comment this tool returns.
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
        $validated = $request->validate([
            'event_id' => ['required', 'integer', Rule::exists(MediaEvent::class, 'id')],
            'media_id' => ['sometimes', 'filled', 'integer', Rule::exists(Media::class, 'id')],
            'event_type' => ['sometimes', 'filled', 'string', Rule::enum(MediaEventTypeName::class)],
            'occurred_on' => ['sometimes', 'filled', 'string', 'date_format:Y-m-d'],
            'replace_comment' => ['sometimes', 'prohibits:append_to_comment', 'string'],
            'append_to_comment' => ['sometimes', 'string', 'filled'],
        ], [
            'occurred_on.date_format' => 'The occurred on field must be a date in the form YYYY-MM-DD, such as 2026-03-15.',
            'append_to_comment.filled' => 'The append to comment field must not be empty. To clear the comment, pass replace_comment as an empty string.',
        ]);

        $event = MediaEvent::with('media')->findOrFail($validated['event_id']);
        $user = $request->user();

        // Every ability this tool can exercise, checked whatever the call asks
        // for: a caller who may not do all of them may not use the tool. Only
        // after loading the event, since MediaEventPolicy is asked about it.
        if ($user?->cannot('update', $event) ?? true) {
            return Response::error('You are not authorized to edit this event.');
        }

        // The response includes the event's comment.
        if ($user->cannot('view', $event)) {
            return Response::error('You are not authorized to read this event.');
        }

        if (array_keys($validated) === ['event_id']) {
            return Response::error('Nothing to change: pass at least one field to edit alongside event_id.');
        }

        // Plan the whole edit before writing anything: apply the fields to the
        // unsaved model, then read what changed from it.
        $movedFrom = $event->media;

        $this->applyFields($event, $validated);

        $changedFields = $this->changedFields($event);
        $moved = $event->isDirty('media_id');

        $event->save();

        $event->load(['media', 'mediaEventType']);

        return Response::structured([
            'event_id' => $event->id,
            'media_id' => $event->media->id,
            'title' => $event->media->title,
            'event_type' => $event->mediaEventType->name->value,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'comment' => $event->comment,
            'moved_from' => $moved
                ? ['media_id' => $movedFrom->id, 'title' => $movedFrom->title]
                : null,
            'changed_fields' => $changedFields,
        ]);
    }

    /**
     * Apply the supplied fields to the unsaved model.
     *
     * @param  array{media_id?: int, event_type?: string, occurred_on?: string, replace_comment?: string, append_to_comment?: string}  $validated
     */
    private function applyFields(MediaEvent $event, array $validated): void
    {
        if (array_key_exists('media_id', $validated)) {
            $event->media_id = $validated['media_id'];
        }

        if (array_key_exists('event_type', $validated)) {
            $event->media_event_type_id = MediaEventType::where('name', MediaEventTypeName::from($validated['event_type']))->sole()->id;
        }

        if (array_key_exists('occurred_on', $validated)) {
            $event->occurred_at = $this->occurredAt($validated['occurred_on']);
        }

        if (array_key_exists('replace_comment', $validated)) {
            $event->comment = $validated['replace_comment'] === '' ? null : $validated['replace_comment'];
        }

        if (array_key_exists('append_to_comment', $validated)) {
            $event->comment = $event->comment === null || $event->comment === ''
                ? $validated['append_to_comment']
                : $event->comment."\n".$validated['append_to_comment'];
        }
    }

    /**
     * Noon UTC on the given date, as create-media-event stores it.
     */
    private function occurredAt(string $occurredOn): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $occurredOn, 'UTC')->setTime(12, 0);
    }

    /**
     * The fields whose stored value this edit changes, in tool vocabulary.
     *
     * @return list<'media_id'|'event_type'|'occurred_on'|'comment'>
     */
    private function changedFields(MediaEvent $event): array
    {
        $fieldsByColumn = [
            'media_id' => 'media_id',
            'media_event_type_id' => 'event_type',
            'occurred_at' => 'occurred_on',
            'comment' => 'comment',
        ];

        return array_values(array_intersect_key($fieldsByColumn, $event->getDirty()));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()
                ->required()
                ->description('The id of the event to edit, as returned in query-media\'s history or by create-media-event.'),
            'media_id' => $schema->integer()
                ->description('Moves the event to this media item, for an event logged against the wrong one. Omit to leave the event on its current item.'),
            'event_type' => $schema->string()
                ->enum(array_column(MediaEventTypeName::cases(), 'value'))
                ->description('The corrected event type. Omit to keep the current one.'),
            'occurred_on' => $schema->string()
                ->description('The corrected date, as YYYY-MM-DD (2026-03-15). No time: it is stored at noon UTC on that date. Resolve relative dates such as "yesterday" yourself; they are refused. Omit to keep the current date.'),
            'replace_comment' => $schema->string()
                ->description('Replaces the event\'s private comment entirely. Pass an empty string to clear it. Cannot be combined with append_to_comment. Omit to keep the current comment.'),
            'append_to_comment' => $schema->string()
                ->description('Text to add to the event\'s private comment, on a new line after what is there. Must not be empty. Cannot be combined with replace_comment.'),
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
            'event_id' => $schema->integer()->description('The id of the event.'),
            'media_id' => $schema->integer()->description('The id of the media item the event now belongs to.'),
            'title' => $schema->string()->description('The title of the item the event now belongs to.'),
            'event_type' => $schema->string()->description('One of: started, finished, abandoned, comment.'),
            'occurred_at' => $schema->string()->description('When the event happened as now stored (ISO 8601).'),
            'comment' => $schema->string()->nullable()->description('The event\'s whole comment as it now reads. Private.'),
            'moved_from' => $schema->object([
                'media_id' => $schema->integer()->description('The id of the item the event was moved from.'),
                'title' => $schema->string()->description('The title of the item the event was moved from.'),
            ])
                ->nullable()
                ->description('The item this call moved the event from. Null when the event stayed where it was.'),
            'changed_fields' => $schema->array()
                ->items($schema->string()->enum(['media_id', 'event_type', 'occurred_on', 'comment']))
                ->description('The fields whose stored value this call changed. Empty when every supplied value matched what was already stored.'),
        ];
    }
}
