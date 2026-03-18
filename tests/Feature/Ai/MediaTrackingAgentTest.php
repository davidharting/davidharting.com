<?php

use App\Ai\Agents\MediaTrackingAgent;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Attributes\Provider;

test('MediaTrackingAgent can be instantiated', function () {
    /** @var TestCase $this */
    $agent = MediaTrackingAgent::make();

    $this->assertInstanceOf(MediaTrackingAgent::class, $agent);
});

test('MediaTrackingAgent uses the anthropic provider', function () {
    /** @var TestCase $this */
    $reflection = new ReflectionClass(MediaTrackingAgent::class);
    $attributes = $reflection->getAttributes(Provider::class);

    $this->assertNotEmpty($attributes);
    $this->assertSame('anthropic', $attributes[0]->newInstance()->value);
});

test('MediaTrackingAgent has instructions', function () {
    /** @var TestCase $this */
    $agent = MediaTrackingAgent::make();

    $this->assertNotEmpty($agent->instructions());
});
