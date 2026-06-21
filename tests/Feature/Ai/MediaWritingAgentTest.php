<?php

use App\Ai\Agents\MediaWritingAgent;
use App\Ai\Tools\CreateMedia;
use App\Ai\Tools\CreateMediaEvent;
use App\Ai\Tools\SearchMedia;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Enums\Lab;

test("uses Anthropic's Sonnet 4.6", function () {
    /** @var TestCase $this */
    $reflection = new ReflectionClass(MediaWritingAgent::class);

    $providerAttributes = $reflection->getAttributes(Provider::class);
    $this->assertNotEmpty($providerAttributes);
    $this->assertSame(Lab::Anthropic, $providerAttributes[0]->newInstance()->value);

    $modelAttributes = $reflection->getAttributes(Model::class);
    $this->assertNotEmpty($modelAttributes);
    $this->assertSame('claude-sonnet-4-6', $modelAttributes[0]->newInstance()->value);
});

test('acts as a tool with a stable name and a description', function () {
    /** @var TestCase $this */
    $agent = new MediaWritingAgent;

    $this->assertInstanceOf(CanActAsTool::class, $agent);
    $this->assertSame('MediaWritingAgent', $agent->name());
    $this->assertNotEmpty((string) $agent->description());
});

test('instructions include the current date for event logging', function () {
    /** @var TestCase $this */
    $instructions = (string) (new MediaWritingAgent)->instructions();

    $this->assertStringContainsString(now()->toDateString(), $instructions);
});

test('owns the database write tools', function () {
    /** @var TestCase $this */
    $tools = collect((new MediaWritingAgent)->tools());

    $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof SearchMedia));
    $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof CreateMedia));
    $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof CreateMediaEvent));
});
