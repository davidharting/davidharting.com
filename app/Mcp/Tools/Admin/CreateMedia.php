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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * Find or create a media item, registered only on AdminServer.
 *
 * Unlike App\Ai\Tools\CreateMedia, this does not use firstOrCreate: that
 * matches the title and creator name case-sensitively, and when the row
 * already exists it silently drops the year and remark passed in the same
 * call. Here both names match case-insensitively, and an existing match is
 * reported along with every supplied field that was not applied.
 */
#[IsIdempotent]
#[IsDestructive(false)]
#[Description(<<<'TEXT'
    Add a book, album, movie, TV show or video game to David Harting's media
    library, or find it if it is already there. Adding an item does not mark it
    started or finished — record that separately as a media event.

    An item is identified by its title, media type and creator. Title and
    creator are matched case-insensitively, so "dune" by "frank herbert" finds
    "Dune" by "Frank Herbert". A new item or creator is stored exactly as
    written here, so use the proper capitalisation.

    When the item already exists nothing is changed: the response says
    media_created=false, returns the item as it is stored, and lists in
    ignored_fields any year or remark you supplied that differs from what is
    stored. Tell David when that happens — changing an existing item is a
    separate, deliberate edit.

    Search with query-media first when you are unsure whether the item exists
    or how its title and creator are spelled in the library.
    TEXT)]
class CreateMedia extends Tool
{
    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found": both resolve through the same filtered collection.
     *
     * handle() asks the narrower questions of whether the caller may create
     * media and creators, and read the remark this tool returns.
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

        if ($user?->cannot('create', Media::class) ?? true) {
            return Response::error('You are not authorized to add media.');
        }

        // The response carries the stored remark, which MediaPolicy::seeNote
        // guards on the website too.
        if ($user->cannot('seeNote', Media::class)) {
            return Response::error('You are not authorized to read David\'s remarks.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'media_type' => ['required', 'string', Rule::enum(MediaTypeName::class)],
            'creator' => ['sometimes', 'string', 'filled', 'max:255'],
            'year' => ['sometimes', 'integer'],
            'remark' => ['sometimes', 'string', 'filled'],
        ]);

        $mediaType = MediaType::where('name', MediaTypeName::from($validated['media_type']))->sole();

        return DB::transaction(function () use ($validated, $mediaType, $user): Response|ResponseFactory {
            $creator = null;

            if (isset($validated['creator'])) {
                $creator = Creator::query()->named($validated['creator'])->first();

                if ($creator === null) {
                    if ($user->cannot('create', Creator::class)) {
                        return Response::error('You are not authorized to add creators.');
                    }

                    $creator = Creator::create(['name' => $validated['creator']]);
                }
            }

            $media = Media::query()
                ->identifiedBy($validated['title'], $mediaType->id, $creator?->id)
                ->first();

            $ignoredFields = [];

            if ($media === null) {
                $media = Media::create([
                    'title' => $validated['title'],
                    'media_type_id' => $mediaType->id,
                    'creator_id' => $creator?->id,
                    'year' => $validated['year'] ?? null,
                    'note' => $validated['remark'] ?? null,
                ]);
            } else {
                if (isset($validated['year']) && $validated['year'] !== $media->year) {
                    $ignoredFields[] = 'year';
                }

                if (isset($validated['remark']) && $validated['remark'] !== $media->note) {
                    $ignoredFields[] = 'remark';
                }
            }

            return Response::structured([
                'media_id' => $media->id,
                'title' => $media->title,
                'media_type' => $mediaType->name->value,
                'creator' => $creator?->name,
                'year' => $media->year,
                'remark' => $media->note,
                'media_created' => $media->wasRecentlyCreated,
                'creator_created' => $creator?->wasRecentlyCreated ?? false,
                'ignored_fields' => $ignoredFields,
            ]);
        });
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->required()
                ->description('The title of the work. Matched case-insensitively; stored as written when the item is new.'),
            'media_type' => $schema->string()
                ->required()
                ->enum(array_column(MediaTypeName::cases(), 'value'))
                ->description('The type of media.'),
            'creator' => $schema->string()
                ->description('The creator — author, director, artist, studio, etc. Matched case-insensitively against existing creators, and created as written when there is no match. Omit only when the work genuinely has no creator; an item with no creator is a different item from the same title with one.'),
            'year' => $schema->integer()
                ->description('The release year of the work itself, not when David engaged with it. Only applied when the item is new.'),
            'remark' => $schema->string()
                ->description('David\'s private standing remark on the item. Only applied when the item is new. Not shown on the public website.'),
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
            'title' => $schema->string()->description('The title as stored, which may differ in case from the one supplied.'),
            'media_type' => $schema->string()->description('One of: album, book, movie, tv show, video game.'),
            'creator' => $schema->string()->nullable()->description('The creator\'s name as stored, or null when the item has no creator.'),
            'year' => $schema->integer()->nullable()->description('The release year as stored.'),
            'remark' => $schema->string()->nullable()->description('David\'s private remark as stored. Not on the public website.'),
            'media_created' => $schema->boolean()->description('True when this call added the item; false when it already existed and nothing was changed.'),
            'creator_created' => $schema->boolean()->description('True when this call added the creator.'),
            'ignored_fields' => $schema->array()
                ->items($schema->string()->enum(['year', 'remark']))
                ->description('Supplied fields that were not applied because the item already existed and stores a different value. Empty when the item is new.'),
        ];
    }
}
