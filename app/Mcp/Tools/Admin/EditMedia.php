<?php

namespace App\Mcp\Tools\Admin;

use App\Enum\MediaTypeName;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Correct a media item's own fields, registered only on AdminServer.
 *
 * Omitting a field always leaves it untouched: the caller is a language model
 * improvising arguments, and reading omission as "clear" would let one fixing
 * a year silently wipe a standing remark. Clearing is an explicit empty value.
 *
 * Not annotated idempotent, because append_to_remark accumulates. Every other
 * edit is idempotent — repeating it changes nothing further — but the
 * annotation is a single flag per tool, so the description says so instead.
 *
 * Nothing in the database enforces the media identity of title, media type
 * and creator, so an edit that would make this item the same work as another
 * is refused here, naming the other item. "Same work" follows create-media: an
 * item recorded with no creator matches any item with its title and type.
 */
#[IsDestructive]
#[Description(<<<'TEXT'
    Correct a media item already in David Harting's library: a misspelt title,
    the wrong release year or media type, a creator that is wrong or was never
    recorded, or David's private remark on it. Find its media_id with
    query-media first.

    Use this to fix an item, not to turn it into a different work: its events
    stay with it, so renaming a wrongly added item into another one would give
    that work a history it does not have. If an item should not exist at all,
    tell David rather than editing it into something else.

    Only the fields you pass are changed; any field you leave out is kept
    exactly as it is. To clear the year pass null, and to clear the remark
    pass replace_remark as an empty string. Title, media type and creator
    cannot be cleared. Repeating an edit changes nothing further, except
    append_to_remark, which adds its text again.

    An item is identified by its title, media type and creator, compared
    case-insensitively, and an older item recorded with no creator counts as
    the same work as any item with its title and media type — the same rule
    create-media uses. An edit that would make this item the same work as
    another is refused and names the other item's media_id. Nothing is changed
    in that case; tell David, since the two are probably duplicates. If they
    are different works and one has no creator, giving it its creator resolves
    the refusal.

    The remark is replaced with replace_remark or added to with
    append_to_remark, which adds your text on a new line after what is there.
    Prefer append_to_remark unless David asked to rewrite the remark. The
    response returns the whole remark as it now reads.
    TEXT)]
class EditMedia extends Tool
{
    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found": both resolve through the same filtered collection.
     *
     * handle() asks the narrower questions of whether the caller may edit this
     * item, add a creator, and read the remark this tool returns.
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
            'media_id' => ['required', 'integer', Rule::exists(Media::class, 'id')],
            'title' => ['sometimes', 'string', 'filled', 'max:255'],
            'media_type' => ['sometimes', 'string', Rule::enum(MediaTypeName::class)],
            'creator' => ['sometimes', 'prohibits:creator_id', 'string', 'filled', 'max:255'],
            'creator_id' => ['sometimes', 'integer', Rule::exists(Creator::class, 'id')],
            'year' => ['sometimes', 'nullable', 'integer'],
            'replace_remark' => ['sometimes', 'prohibits:append_to_remark', 'string'],
            'append_to_remark' => ['sometimes', 'string', 'filled'],
        ], [
            'append_to_remark.filled' => 'The append to remark field must not be empty. To clear the remark, pass replace_remark as an empty string.',
        ]);

        $media = Media::findOrFail($validated['media_id']);
        $user = $request->user();

        // Every ability this tool can exercise, checked whatever the call asks
        // for: a caller who may not do all of them may not use the tool. Only
        // after loading the item, since MediaPolicy::update is asked about it.
        if ($user?->cannot('update', $media) ?? true) {
            return Response::error('You are not authorized to edit this item.');
        }

        if ($user->cannot('create', Creator::class)) {
            return Response::error('You are not authorized to add creators.');
        }

        // The response includes the stored remark, which MediaPolicy::seeNote
        // guards on the website too.
        if ($user->cannot('seeNote', Media::class)) {
            return Response::error('You are not authorized to read David\'s remarks.');
        }

        if (array_keys($validated) === ['media_id']) {
            return Response::error('Nothing to change: pass at least one field to edit alongside media_id.');
        }

        // Plan the whole edit before writing anything: resolve the creator,
        // apply the fields to the unsaved model, and check the identity.
        $creator = $this->resolveCreator($validated);
        $creatorIsNew = $creator !== null && ! $creator->exists;

        $this->applyFields($media, $validated, $creator);

        if ($creatorIsNew || $media->isDirty(['title', 'media_type_id', 'creator_id'])) {
            $conflict = $this->findSameWork($media, $creatorIsNew);

            if ($conflict !== null) {
                return Response::error($this->conflictMessage($conflict, $media, $creatorIsNew));
            }
        }

        $changedFields = $this->changedFields($media, $creatorIsNew);

        // The transaction only keeps the two writes together. It does not
        // guard the identity check against a concurrent insert: at Postgres's
        // default READ COMMITTED isolation it could not.
        DB::transaction(function () use ($media, $creator, $creatorIsNew): void {
            if ($creatorIsNew) {
                $creator->save();
                $media->creator_id = $creator->id;
            }

            $media->save();
        });

        $media->load(['mediaType', 'creator']);

        return Response::structured([
            'media_id' => $media->id,
            'title' => $media->title,
            'media_type' => $media->mediaType->name->value,
            'creator' => $media->creator?->name,
            'year' => $media->year,
            'remark' => $media->note,
            'creator_created' => $creatorIsNew,
            'changed_fields' => $changedFields,
        ]);
    }

    /**
     * Apply the supplied fields to the unsaved model. A creator that does not
     * exist yet has no id to apply; it is saved and attached with the write.
     *
     * @param  array{title?: string, media_type?: string, year?: ?int, replace_remark?: string, append_to_remark?: string}  $validated
     */
    private function applyFields(Media $media, array $validated, ?Creator $creator): void
    {
        if (array_key_exists('title', $validated)) {
            $media->title = $validated['title'];
        }

        if (array_key_exists('media_type', $validated)) {
            $media->media_type_id = MediaType::where('name', MediaTypeName::from($validated['media_type']))->sole()->id;
        }

        if ($creator?->exists) {
            $media->creator_id = $creator->id;
        }

        if (array_key_exists('year', $validated)) {
            $media->year = $validated['year'];
        }

        if (array_key_exists('replace_remark', $validated)) {
            $media->note = $validated['replace_remark'] === '' ? null : $validated['replace_remark'];
        }

        if (array_key_exists('append_to_remark', $validated)) {
            $media->note = $media->note === null || $media->note === ''
                ? $validated['append_to_remark']
                : $media->note."\n".$validated['append_to_remark'];
        }
    }

    /**
     * Another item that the edited one would be the same work as, by the rule
     * create-media uses: title and media type match case-insensitively, and
     * the creators match or either item has none. An exact match is preferred.
     *
     * A creator that does not exist yet has no other works, so with one only
     * an item recorded with no creator can match.
     */
    private function findSameWork(Media $media, bool $creatorIsNew): ?Media
    {
        $query = Media::query()
            ->whereRaw('lower(title) = lower(?)', [$media->title])
            ->where('media_type_id', $media->media_type_id)
            ->whereKeyNot($media->id);

        if ($creatorIsNew) {
            $query->whereNull('creator_id');
        } elseif ($media->creator_id !== null) {
            $query->where(fn ($query) => $query->where('creator_id', $media->creator_id)->orWhereNull('creator_id'))
                ->orderByRaw('creator_id is null');
        }

        return $query->with('creator')->first();
    }

    /**
     * Why the edit was refused, naming the other item, and how to resolve it
     * when the two turn out to be different works.
     */
    private function conflictMessage(Media $conflict, Media $media, bool $creatorIsNew): string
    {
        $refused = sprintf('Refused: media_id %d, "%s"', $conflict->id, $conflict->title);

        if ($conflict->creator === null) {
            return $refused.sprintf(
                ', has the same title and media type and was recorded with no creator, so it counts as the same work. Nothing was changed. Tell David, since the two are probably duplicates. If they are different works, give media_id %d its creator first.',
                $conflict->id,
            );
        }

        if ($media->creator_id === null && ! $creatorIsNew) {
            return $refused.sprintf(
                ' by %s, has the same title and media type, and this item has no creator, so they count as the same work. Nothing was changed. Tell David, since the two are probably duplicates. If they are different works, give this item its creator in the same edit.',
                $conflict->creator->name,
            );
        }

        return $refused.sprintf(
            ' by %s, already has that title, media type and creator. Nothing was changed. Tell David, since the two are probably duplicates.',
            $conflict->creator->name,
        );
    }

    /**
     * The creator to give the item: an existing one by id or by name, or an
     * unsaved new one when the name matches none. Null when neither is given.
     *
     * @param  array{creator?: string, creator_id?: int}  $validated
     */
    private function resolveCreator(array $validated): ?Creator
    {
        if (array_key_exists('creator_id', $validated)) {
            return Creator::findOrFail($validated['creator_id']);
        }

        if (array_key_exists('creator', $validated)) {
            return Creator::query()->named($validated['creator'])->first()
                ?? new Creator(['name' => $validated['creator']]);
        }

        return null;
    }

    /**
     * The fields whose stored value this edit changes, in tool vocabulary. A
     * new creator is not applied until the write, so it is counted here.
     *
     * @return list<'title'|'media_type'|'creator'|'year'|'remark'>
     */
    private function changedFields(Media $media, bool $creatorIsNew): array
    {
        $fieldsByColumn = [
            'title' => 'title',
            'media_type_id' => 'media_type',
            'creator_id' => 'creator',
            'year' => 'year',
            'note' => 'remark',
        ];

        $dirty = $media->getDirty();

        if ($creatorIsNew) {
            $dirty['creator_id'] = true;
        }

        return array_values(array_intersect_key($fieldsByColumn, $dirty));
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
                ->description('The id of the media item to edit, as returned by query-media or create-media.'),
            'title' => $schema->string()
                ->description('The corrected title, stored exactly as written, so use the proper capitalisation. Omit to keep the current title.'),
            'media_type' => $schema->string()
                ->enum(array_column(MediaTypeName::cases(), 'value'))
                ->description('The corrected media type. Omit to keep the current one.'),
            'creator' => $schema->string()
                ->description('The corrected creator, by name. Matched case-insensitively against existing creators, and created as written when there is no match. This re-points the item only: other works by its current creator are unaffected. Give at most one of creator or creator_id; omit both to keep the current creator.'),
            'creator_id' => $schema->integer()
                ->description('The id of a creator already in the library, as returned by query-media. Prefer this over creator whenever you know it. Give at most one of creator or creator_id.'),
            'year' => $schema->integer()
                ->nullable()
                ->description('The corrected release year of the work itself, not when David engaged with it. Pass null to clear it; omit to keep the current year.'),
            'replace_remark' => $schema->string()
                ->description('Replaces David\'s private remark entirely. Pass an empty string to clear it. Cannot be combined with append_to_remark. Omit to keep the current remark.'),
            'append_to_remark' => $schema->string()
                ->description('Text to add to David\'s private remark, on a new line after what is there. Must not be empty. Cannot be combined with replace_remark.'),
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
            'media_id' => $schema->integer()->description('The internal id of the media item.'),
            'title' => $schema->string()->description('The title as now stored.'),
            'media_type' => $schema->string()->description('One of: album, book, movie, tv show, video game.'),
            'creator' => $schema->string()->nullable()->description('The creator\'s name as now stored. Null only for an older item recorded with no creator that this edit did not give one.'),
            'year' => $schema->integer()->nullable()->description('The release year as now stored.'),
            'remark' => $schema->string()->nullable()->description('David\'s whole private remark as it now reads. Not on the public website.'),
            'creator_created' => $schema->boolean()->description('True when this call added the creator to the library.'),
            'changed_fields' => $schema->array()
                ->items($schema->string()->enum(['title', 'media_type', 'creator', 'year', 'remark']))
                ->description('The fields whose stored value this call changed. Empty when every supplied value matched what was already stored.'),
        ];
    }
}
