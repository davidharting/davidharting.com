<?php

use App\Ai\Agents\WebSearchAgent;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Tools\WebSearch;

test("uses Anthropic's Sonnet 4.6", function () {
    /** @var TestCase $this */
    $reflection = new ReflectionClass(WebSearchAgent::class);

    $providerAttributes = $reflection->getAttributes(Provider::class);
    $this->assertNotEmpty($providerAttributes);
    $this->assertSame(Lab::Anthropic, $providerAttributes[0]->newInstance()->value);

    $modelAttributes = $reflection->getAttributes(Model::class);
    $this->assertNotEmpty($modelAttributes);
    $this->assertSame('claude-sonnet-4-6', $modelAttributes[0]->newInstance()->value);
});

test('acts as a tool with a stable name and a description', function () {
    /** @var TestCase $this */
    $agent = new WebSearchAgent;

    $this->assertInstanceOf(CanActAsTool::class, $agent);
    $this->assertSame('WebSearchAgent', $agent->name());
    $this->assertNotEmpty((string) $agent->description());
});

test('instructions frame the agent as a context-free shim', function () {
    /** @var TestCase $this */
    $instructions = (string) (new WebSearchAgent)->instructions();

    $this->assertStringContainsString('complete specification', $instructions);
    $this->assertStringContainsStringIgnoringCase('WebSearch', $instructions);
});

test('has the WebSearch provider tool', function () {
    /** @var TestCase $this */
    $tools = collect((new WebSearchAgent)->tools());

    $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof WebSearch));
});
