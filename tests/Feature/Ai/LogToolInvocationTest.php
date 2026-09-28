<?php

use App\Listeners\LogToolInvocation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Tools\Request;

test('the listener is registered for ToolInvoked', function () {
    /** @var TestCase $this */
    Event::fake();

    Event::assertListening(ToolInvoked::class, LogToolInvocation::class);
});

test('logs the agent, tool, ids, arguments, and result', function () {
    /** @var TestCase $this */
    Log::spy();

    $agent = new AnonymousAgent('Be helpful.', [], []);

    $tool = new class implements Tool
    {
        public function description(): string
        {
            return 'Look something up.';
        }

        public function handle(Request $request): string
        {
            return '';
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };

    $event = new ToolInvoked(
        invocationId: 'inv-1',
        toolInvocationId: 'tool-inv-1',
        agent: $agent,
        tool: $tool,
        arguments: ['title' => 'Dune'],
        result: '{"found":false,"results":[]}',
    );

    (new LogToolInvocation)->handle($event);

    Log::shouldHaveReceived('info')->once()->withArgs(function ($message, $context) use ($agent, $tool) {
        return $message === 'AI tool invoked'
            && $context['agent'] === $agent::class
            && $context['tool'] === $tool::class
            && $context['invocation_id'] === 'inv-1'
            && $context['tool_invocation_id'] === 'tool-inv-1'
            && $context['arguments'] === ['title' => 'Dune']
            && $context['result'] === '{"found":false,"results":[]}';
    });
});
