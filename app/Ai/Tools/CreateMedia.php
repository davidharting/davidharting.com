<?php

namespace App\Ai\Tools;

use App\Enum\MediaTypeName;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateMedia implements Tool
{
    public function description(): Stringable|string
    {
        return <<<'TEXT'
            Find or create a media item in the library. Provide either creator_id (when the
            creator was found via SearchMedia) or creator_name (when the creator is new —
            the tool will create the creator automatically). Exactly one of creator_id or
            creator_name must be provided. Returns the media record details and whether it
            was newly created.
            TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $creatorId = $request->integer('creator_id') ?: null;
        $creatorName = ((string) $request->string('creator_name')) ?: null;

        if ($creatorId === null && $creatorName === null) {
            return json_encode(
                ['error' => 'Exactly one of creator_id or creator_name must be provided. Neither was given.'],
                JSON_THROW_ON_ERROR,
            );
        }

        if ($creatorId !== null && $creatorName !== null) {
            return json_encode(
                ['error' => 'Exactly one of creator_id or creator_name must be provided. Both were given.'],
                JSON_THROW_ON_ERROR,
            );
        }

        $mediaTypeString = (string) $request->string('media_type');
        $mediaTypeName = MediaTypeName::tryFrom(strtolower($mediaTypeString));

        if ($mediaTypeName === null) {
            $valid = implode(', ', array_column(MediaTypeName::cases(), 'value'));

            return json_encode(
                ['error' => "Invalid media_type \"{$mediaTypeString}\". Must be one of: {$valid}."],
                JSON_THROW_ON_ERROR,
            );
        }

        $creator = $creatorId !== null
            ? Creator::find($creatorId)
            : Creator::firstOrCreate(['name' => $creatorName]);

        if ($creator === null) {
            return json_encode(
                ['error' => "Creator with id {$creatorId} not found."],
                JSON_THROW_ON_ERROR,
            );
        }

        $mediaType = MediaType::where('name', $mediaTypeName)->sole();

        $title = (string) $request->string('title');
        $year = $request->integer('year') ?: null;
        $note = ((string) $request->string('note')) ?: null;

        $media = Media::firstOrCreate(
            [
                'title' => $title,
                'media_type_id' => $mediaType->id,
                'creator_id' => $creator->id,
            ],
            [
                'year' => $year,
                'note' => $note,
            ],
        );

        return json_encode([
            'media_id' => $media->id,
            'title' => $media->title,
            'year' => $media->year,
            'creator' => $creator->name,
            'media_type' => $mediaTypeName->value,
            'created' => $media->wasRecentlyCreated,
        ], JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required()
                ->description('The title of the media item.'),
            'year' => $schema->integer()
                ->description('The publication or release year (optional).'),
            'creator_id' => $schema->integer()
                ->description('The creator\'s ID as returned by SearchMedia. Use when the creator already exists in the library.'),
            'creator_name' => $schema->string()
                ->description('The creator\'s name. Use when the creator is new — the tool will call firstOrCreate on Creator.'),
            'media_type' => $schema->string()->required()
                ->enum(array_column(MediaTypeName::cases(), 'value'))
                ->description('The type of media.'),
            'note' => $schema->string()
                ->description('An optional personal note about the media item.'),
        ];
    }
}
