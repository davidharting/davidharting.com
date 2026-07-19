<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\RecoverableMcpServerTool;
use App\Listeners\LogToolInvocation;
use App\Mcp\Tools\QueryMedia;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\ToolInvoked;

test('the listener is registered for ToolInvoked', function () {
    /** @var TestCase $this */
    Event::fake();

    Event::assertListening(ToolInvoked::class, LogToolInvocation::class);
});

test('logs the agent, tool, ids, arguments, and result', function () {
    /** @var TestCase $this */
    Log::spy();

    $event = new ToolInvoked(
        invocationId: 'inv-1',
        toolInvocationId: 'tool-inv-1',
        agent: new MediaTrackingAgent,
        tool: new RecoverableMcpServerTool(new QueryMedia),
        arguments: ['title' => 'Dune'],
        result: '{"total":0,"results":[]}',
    );

    (new LogToolInvocation)->handle($event);

    Log::shouldHaveReceived('info')->once()->withArgs(function ($message, $context) {
        return $message === 'AI tool invoked'
            && $context['agent'] === MediaTrackingAgent::class
            && $context['tool'] === RecoverableMcpServerTool::class
            && $context['tool_name'] === 'query-media'
            && $context['invocation_id'] === 'inv-1'
            && $context['tool_invocation_id'] === 'tool-inv-1'
            && $context['arguments'] === ['title' => 'Dune']
            && $context['result'] === '{"total":0,"results":[]}';
    });
});
