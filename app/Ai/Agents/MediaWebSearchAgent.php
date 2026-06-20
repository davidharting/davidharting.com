<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;
use Stringable;

#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-6')]
class MediaWebSearchAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    /**
     * Get the name the parent agent uses to invoke this sub-agent as a tool.
     */
    public function name(): string
    {
        return 'MediaWebSearchAgent';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Identify a piece of media via web search. Pass a condensed description of what the user told you about the media — NOT a search query string. Returns 0, 1, or multiple candidate matches as markdown bullets.';
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
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

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [new WebSearch];
    }
}
