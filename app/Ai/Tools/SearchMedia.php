<?php

namespace App\Ai\Tools;

use App\Enum\MediaTypeName;
use App\Queries\Media\SearchMediaQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchMedia implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return <<<'TEXT'
            Search for media items in the library by title and/or creator. Optionally
            filter by media_type (album, book, movie, tv show, video game) to
            disambiguate when multiple items share a title. At least one of title or
            creator must be provided. Returns matching records including the title,
            year, media type, creator, current tracking status (backlog, started,
            finished, or abandoned), and the dates each status was reached. Use this
            after identifying the media item to check if it is already in the library
            and what its current status is.
            TEXT;
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $title = ((string) $request->string('title')) ?: null;
        $creator = ((string) $request->string('creator')) ?: null;
        $mediaTypeString = ((string) $request->string('media_type')) ?: null;
        $mediaType = $mediaTypeString !== null ? MediaTypeName::tryFrom(strtolower($mediaTypeString)) : null;

        if ($mediaTypeString !== null && $mediaType === null) {
            $valid = implode(', ', array_column(MediaTypeName::cases(), 'value'));

            return json_encode(
                ['error' => "Invalid media_type \"{$mediaTypeString}\". Must be one of: {$valid}."],
                JSON_THROW_ON_ERROR,
            );
        }

        if ($title === null && $creator === null) {
            return json_encode(
                ['error' => 'At least one of title or creator must be provided.'],
                JSON_THROW_ON_ERROR,
            );
        }

        $results = (new SearchMediaQuery(
            title: $title,
            mediaType: $mediaType,
            creator: $creator,
        ))->execute();

        Log::info('SearchMedia tool called', [
            'input' => [
                'title' => $title,
                'creator' => $creator,
                'media_type' => $mediaType?->value,
            ],
            'result_count' => $results->count(),
        ]);

        if ($results->isEmpty()) {
            return json_encode(['found' => false, 'results' => []], JSON_THROW_ON_ERROR);
        }

        return json_encode(['found' => true, 'results' => $results->toArray()], JSON_THROW_ON_ERROR);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('The title of the media item to search for (case-insensitive, partial match).'),
            'creator' => $schema->string()
                ->description('The creator (author, director, artist, etc.) to search for (case-insensitive, partial match).'),
            'media_type' => $schema->string()
                ->enum(array_column(MediaTypeName::cases(), 'value'))
                ->description('Optional media type filter.'),
        ];
    }
}
