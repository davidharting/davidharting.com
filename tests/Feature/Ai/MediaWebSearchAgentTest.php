<?php

use App\Ai\Agents\MediaWebSearchAgent;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Tools\WebSearch;

test("uses Anthropic's Sonnet 4.6", function () {
    /** @var TestCase $this */
    $reflection = new ReflectionClass(MediaWebSearchAgent::class);

    $providerAttributes = $reflection->getAttributes(Provider::class);
    $this->assertNotEmpty($providerAttributes);
    $this->assertSame(Lab::Anthropic, $providerAttributes[0]->newInstance()->value);

    $modelAttributes = $reflection->getAttributes(Model::class);
    $this->assertNotEmpty($modelAttributes);
    $this->assertSame('claude-sonnet-4-6', $modelAttributes[0]->newInstance()->value);
});

test('acts as a tool with a stable name and a description', function () {
    /** @var TestCase $this */
    $agent = new MediaWebSearchAgent;

    $this->assertInstanceOf(CanActAsTool::class, $agent);
    $this->assertSame('MediaWebSearchAgent', $agent->name());
    $this->assertNotEmpty((string) $agent->description());
});

test('instructions describe the media identification contract', function () {
    /** @var TestCase $this */
    $instructions = (string) (new MediaWebSearchAgent)->instructions();

    $this->assertStringContainsString('No matches found.', $instructions);
    $this->assertStringContainsStringIgnoringCase('media_type', $instructions);
});

test('owns the WebSearch provider tool', function () {
    /** @var TestCase $this */
    $tools = collect((new MediaWebSearchAgent)->tools());

    $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof WebSearch));
});
