<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Tools\Request;
use Stringable;

use function Laravel\Ai\agent;

class MediaWebSearchAgentTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Identify a piece of media via web search. Pass a condensed description of what the user told you about the media — NOT a search query string. Returns 0, 1, or multiple candidate matches as markdown bullets.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = $request->string('query', '');

        if ($query->isEmpty()) {
            return json_encode(
                ['error' => 'query must not be empty. Pass a condensed description of the target media.'],
                JSON_THROW_ON_ERROR,
            );
        }

        Log::info('MediaWebSearchAgentTool called', ['query' => $query]);

        $response = agent(
            instructions: $this->instructions(),
            tools: [new WebSearch],
        )->prompt((string) $query, provider: 'anthropic', model: 'claude-sonnet-4-6');

        return $response->text;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()
                ->description('Condensed description of the target media — NOT a search query string. Include whatever the user said: title, partial title, creator name, year, media type, plot hints, etc. Examples: "novel called Ghostwritten by David Mitchell", "the 2021 Dune movie", "that sci-fi book about sandworms".'),
        ];
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
        You identify a piece of media based on a condensed description provided by an orchestrator agent.

        Use the WebSearch tool to find candidate matches. You may search multiple times if needed to disambiguate.

        **Primary creator by media type**
        - Album → artist
        - Book → author
        - Movie → director
        - TV show → creator or showrunner
        - Video game → developer studio

        Pick one creator only — the single most relevant primary creator. For example, for a movie with multiple directors, pick the lead.

        **Media type values**
        media_type must be one of: album, book, movie, tv show, video game

        **Return format**

        If no plausible match exists, return EXACTLY this text and nothing else:
        No matches found.

        If one or more plausible matches exist, return markdown bullets — one per line — in this exact format:
        - <title> (<year>) — <creator> — <media_type>

        Example with one match:
        - Ghostwritten (1999) — David Mitchell — book

        Example with multiple matches (when the query is ambiguous, e.g. a remake, an adaptation, or multiple works sharing a title):
        - Dune (1965) — Frank Herbert — book
        - Dune (1984) — David Lynch — movie
        - Dune (2021) — Denis Villeneuve — movie

        Return ONLY the bullets (or the "No matches found." sentence). No preamble, no prose, no trailing summary, no explanation. The orchestrator handles disambiguation with the user.
        PROMPT;
    }
}
