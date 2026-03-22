<?php

use App\Ai\Agents\MediaTrackingAgent;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Providers\Tools\WebSearch;

test("MediaTrackingAgent uses Anthropic's Sonnet 4.6", function () {
    /** @var TestCase $this */
    $reflection = new ReflectionClass(MediaTrackingAgent::class);

    $providerAttributes = $reflection->getAttributes(Provider::class);
    $this->assertNotEmpty($providerAttributes);
    $this->assertSame('anthropic', $providerAttributes[0]->newInstance()->value);

    $modelAttributes = $reflection->getAttributes(Model::class);
    $this->assertNotEmpty($modelAttributes);
    $this->assertSame('claude-sonnet-4-6', $modelAttributes[0]->newInstance()->value);
});

test('MediaTrackingAgent instructions mention media tracking and library status', function () {
    /** @var TestCase $this */
    $agent = MediaTrackingAgent::make();
    $instructions = $agent->instructions();

    $this->assertStringContainsStringIgnoringCase('media', $instructions);
    $this->assertStringContainsStringIgnoringCase('status', $instructions);
});

test('MediaTrackingAgent has correct tools', function () {
    /** @var TestCase $this */
    $agent = MediaTrackingAgent::make();
    $tools = collect($agent->tools());

    $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof WebSearch));
    $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof \App\Ai\Tools\SearchMedia));
});
