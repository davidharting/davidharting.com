<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateMedia;
use App\Ai\Tools\CreateMediaEvent;
use App\Ai\Tools\RecoverableMcpServerTool;
use App\Mcp\Tools\QueryMedia;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-6')]
class MediaWritingAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    /**
     * Get the name the parent agent uses to invoke this sub-agent as a tool.
     */
    public function name(): string
    {
        return 'MediaWritingAgent';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Execute a confirmed media tracking plan. Call this after the user confirms, passing your exact plan text.';
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $today = now()->toDateString();

        return <<<PROMPT
        You execute a confirmed media tracking plan by calling the available DB tools.

        Today's date is {$today}.

        **Creator resolution**
        Always call query-media with the creator name first to get creator_id before calling CreateMedia.
        Use partial search — for example, to find J.R.R. Tolkien search for "Tolkien" and inspect the results.
        If a matching creator is found, pass creator_id to CreateMedia.
        If not found, pass creator_name — the tool will create the creator.

        **Backlog only**
        If the plan is to add to the library with no event, call CreateMedia only.

        **Comments on events**
        If the plan includes a remark, note, or comment alongside a started, finished, or abandoned event, pass it as the `comment` parameter to CreateMediaEvent — do NOT create a separate comment event. Only create a standalone comment event when the plan explicitly calls for logging a comment with no other event.

        **Return value**
        Return a concise plain-text summary of exactly what was written.
        Example: "Added The Hobbit (1937) by J.R.R. Tolkien — Book. Logged a started event on March 27, 2026."
        PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [new RecoverableMcpServerTool(new QueryMedia), new CreateMedia, new CreateMediaEvent];
    }
}
