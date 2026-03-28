<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Tools\Request;
use Stringable;

use function Laravel\Ai\agent;

class ResolveMediaTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Identify a media item from a raw reference. Returns a JSON array of matches with title, year, creator, and media_type.';
    }

    public function handle(Request $request): Stringable|string
    {
        $reference = $request->string('reference', '');

        if ($reference->isEmpty()) {
            return json_encode(
                ['error' => 'reference must not be empty.'],
                JSON_THROW_ON_ERROR,
            );
        }

        Log::info('ResolveMediaTool called', ['reference' => (string) $reference]);

        $response = agent(
            instructions: $this->instructions(),
            tools: [new WebSearch],
        )->prompt((string) $reference, provider: 'anthropic', model: 'claude-haiku-4-5-20251001');

        return $response->text;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()->required()
                ->description('Raw media reference from the user\'s message, e.g. "Dune 2021 movie" or "The Hobbit book".'),
        ];
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
        You identify media items from a raw reference.

        Use web search to confirm the exact title, year, primary creator, and media type.

        Primary creator by type:
        - Album → artist
        - Book → author
        - Movie → director
        - TV show → creator or showrunner
        - Video game → developer studio

        Pick one primary creator only.

        Return ONLY a JSON array of matches. Each match must have these keys:
        {"title": "...", "year": 1965, "creator": "...", "media_type": "Book|Movie|Album|TV Show|Video Game"}

        - If there is one clear match, return an array with one item.
        - If there are multiple plausible matches (remake, adaptation, same title different work), return all of them.
        - If nothing is found, return an empty array [].

        No prose. No explanation. Only the JSON array.
        PROMPT;
    }
}
