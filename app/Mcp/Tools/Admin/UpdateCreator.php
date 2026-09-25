<?php

namespace App\Mcp\Tools\Admin;

use App\Models\Creator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * Rename a creator, registered only on AdminServer.
 *
 * The repair for a creator name stored as first given. Creator names render
 * publicly, and edit-media cannot stand in: re-pointing one item leaves the
 * misspelt row in place for every other work by that creator.
 *
 * It never merges. creators.name is unique only case-sensitively, so a rename
 * onto another creator's name in different case would otherwise be accepted
 * and leave two rows the library treats as one. Either way it is refused here,
 * naming the other creator: merging two creators' works is a separate feature.
 *
 * Idempotent, unlike the other edit tools: it has one field and nothing that
 * accumulates, so repeating a rename changes nothing further.
 */
#[IsIdempotent]
#[IsDestructive]
#[Description(<<<'TEXT'
    Correct the name of a creator — author, director, artist, developer — in
    David Harting's media library: a misspelling, the wrong capitalisation, or
    a name recorded incompletely. Find the creator_id with query-media first.

    The name changes everywhere it appears, including on the public website,
    for every work by that creator: the response says how many. To give one
    item a different creator instead, use edit-media.

    The name is stored exactly as written, so use the proper capitalisation.
    A change of case to the creator's own name is allowed.

    Names are compared case-insensitively. A rename onto a name another creator
    already has is refused and names that creator's id; nothing is changed.
    This tool never merges two creators. Tell David, since the two are probably
    duplicates — he merges them himself.
    TEXT)]
class UpdateCreator extends Tool
{
    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found": both resolve through the same filtered collection.
     *
     * handle() asks the narrower question of whether the caller may update
     * this creator.
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
            'creator_id' => ['required', 'integer', Rule::exists(Creator::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $creator = Creator::findOrFail($validated['creator_id']);

        // The only ability this tool exercises. Creators are public, so
        // returning one needs no separate read check.
        if ($request->user()?->cannot('update', $creator) ?? true) {
            return Response::error('You are not authorized to edit this creator.');
        }

        $conflict = Creator::query()
            ->named($validated['name'])
            ->whereKeyNot($creator->id)
            ->first();

        if ($conflict !== null) {
            return Response::error(sprintf(
                'Refused: creator_id %d is already named "%s". Nothing was changed, and this tool never merges creators. Tell David, since the two are probably duplicates.',
                $conflict->id,
                $conflict->name,
            ));
        }

        $previousName = $creator->name;
        $creator->name = $validated['name'];
        $changed = $creator->isDirty('name');

        // The conflict check does not guard against a concurrent rename: at
        // Postgres's default READ COMMITTED isolation it could not.
        $creator->save();

        return Response::structured([
            'creator_id' => $creator->id,
            'name' => $creator->name,
            'previous_name' => $previousName,
            'changed' => $changed,
            'media_count' => $creator->media()->count(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'creator_id' => $schema->integer()
                ->required()
                ->description('The id of the creator to rename, as returned by query-media.'),
            'name' => $schema->string()
                ->required()
                ->description('The corrected name, stored exactly as written, so use the proper capitalisation.'),
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
            'creator_id' => $schema->integer()->description('The id of the creator.'),
            'name' => $schema->string()->description('The name as now stored.'),
            'previous_name' => $schema->string()->description('The name before this call.'),
            'changed' => $schema->boolean()->description('False when the name supplied matched the stored one exactly.'),
            'media_count' => $schema->integer()->description('How many items in the library are by this creator, all of which now show this name.'),
        ];
    }
}
