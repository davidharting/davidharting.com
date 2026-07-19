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

/**
 * A thin wrapper that gives orchestrator agents web search access.
 *
 * Provider-executed tools (like WebSearch) and client-executed tools cannot
 * safely mix in the same agent turn — a PrismPHP bug crashes the run when
 * Claude uses both. Isolating WebSearch inside this sub-agent keeps the
 * orchestrator's turns purely client-side while still allowing arbitrary
 * web research. Intentionally little more than a shim: it owns no domain
 * knowledge, so callers must send self-contained requests.
 */
#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-6')]
class WebSearchAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    /**
     * Get the name the parent agent uses to invoke this sub-agent as a tool.
     */
    public function name(): string
    {
        return 'WebSearchAgent';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search the web. Pass a self-contained natural-language request describing what to find and what details to return — the agent has no other context. Returns concise findings. This is your only way to reach the internet.';
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You perform web research on behalf of an orchestrator agent. The incoming request is your complete specification — you have no other context and cannot ask follow-up questions.

        Use the WebSearch tool to research the request. You may search multiple times to refine or disambiguate.

        Reply with concise, factual findings that answer the request, including any specific fields or format the orchestrator asked for. Cite names, titles, years, and sources where relevant. If you cannot find what was asked for, say plainly what you could not find. No preamble, no trailing summary.
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
