<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Tools\ToolNameResolver;

class LogToolInvocation
{
    /**
     * Log every AI tool invocation in one place.
     *
     * Replaces the per-tool Log::info() calls the tools used to make by hand.
     * Because the event fires for tools nested inside sub-agents too, this also
     * captures invocations the orchestrator never sees directly.
     */
    public function handle(ToolInvoked $event): void
    {
        Log::info('AI tool invoked', [
            'agent' => $event->agent::class,
            'tool' => $event->tool::class,
            'tool_name' => ToolNameResolver::resolve($event->tool),
            'invocation_id' => $event->invocationId,
            'tool_invocation_id' => $event->toolInvocationId,
            'arguments' => $event->arguments,
            'result' => $event->result,
        ]);
    }
}
