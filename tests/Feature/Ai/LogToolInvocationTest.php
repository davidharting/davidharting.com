<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\SearchMedia;
use App\Listeners\LogToolInvocation;
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
        tool: new SearchMedia,
        arguments: ['title' => 'Dune'],
        result: '{"found":false,"results":[]}',
    );

    (new LogToolInvocation)->handle($event);

    Log::shouldHaveReceived('info')->once()->withArgs(function ($message, $context) {
        return $message === 'AI tool invoked'
            && $context['agent'] === MediaTrackingAgent::class
            && $context['tool'] === SearchMedia::class
            && $context['invocation_id'] === 'inv-1'
            && $context['tool_invocation_id'] === 'tool-inv-1'
            && $context['arguments'] === ['title' => 'Dune']
            && $context['result'] === '{"found":false,"results":[]}';
    });
});
