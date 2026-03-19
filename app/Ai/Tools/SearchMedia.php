<?php

namespace App\Ai\Tools;

use App\Queries\Media\SearchMediaQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        return 'Search for a media item in the library by title. Returns matching records including the title, year, media type, creator, current tracking status (backlog, started, finished, or abandoned), and the dates each status was reached. Use this after identifying the media item to check if it is already in the library and what its current status is.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $results = (new SearchMediaQuery(
            title: (string) $request->string('title'),
            mediaType: ((string) $request->string('media_type')) ?: null,
        ))->execute();

        if ($results->isEmpty()) {
            return json_encode(['found' => false, 'results' => []]);
        }

        return json_encode(['found' => true, 'results' => $results->toArray()]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('The title of the media item to search for (case-insensitive, partial match).')
                ->required(),
            'media_type' => $schema->string()
                ->description('Optional media type filter. One of: album, book, movie, tv show, video game.'),
        ];
    }
}
