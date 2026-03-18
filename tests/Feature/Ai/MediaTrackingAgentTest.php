<?php

use App\Ai\Agents\MediaTrackingAgent;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Providers\Tools\WebSearch;

test('MediaTrackingAgent uses the anthropic provider', function () {
    /** @var TestCase $this */
    $reflection = new ReflectionClass(MediaTrackingAgent::class);
    $attributes = $reflection->getAttributes(Provider::class);

    $this->assertNotEmpty($attributes);
    $this->assertSame('anthropic', $attributes[0]->newInstance()->value);
});

test('MediaTrackingAgent uses claude-sonnet-4-6', function () {
    /** @var TestCase $this */
    $reflection = new ReflectionClass(MediaTrackingAgent::class);
    $attributes = $reflection->getAttributes(Model::class);

    $this->assertNotEmpty($attributes);
    $this->assertSame('claude-sonnet-4-6', $attributes[0]->newInstance()->value);
});

test('MediaTrackingAgent instructions mention media tracking', function () {
    /** @var TestCase $this */
    $agent = MediaTrackingAgent::make();

    $this->assertStringContainsString('media', strtolower((string) $agent->instructions()));
});

test('MediaTrackingAgent has WebSearch tool', function () {
    /** @var TestCase $this */
    $agent = MediaTrackingAgent::make();
    $tools = collect($agent->tools());

    $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof WebSearch));
});
