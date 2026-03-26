<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\RequestConfirmation;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
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

test('MediaTrackingAgent messages() returns empty array by default', function () {
    /** @var TestCase $this */
    $agent = new MediaTrackingAgent();

    $this->assertSame([], iterator_to_array($agent->messages()));
});

test('MediaTrackingAgent messages() returns injected history', function () {
    /** @var TestCase $this */
    $history = [
        new Message(MessageRole::User, 'Add The Hobbit'),
        new Message(MessageRole::Assistant, 'Got it!'),
    ];

    $agent = new MediaTrackingAgent(history: $history);

    $this->assertSame($history, iterator_to_array($agent->messages()));
});

test('MediaTrackingAgent tools() includes injected RequestConfirmation instance', function () {
    /** @var TestCase $this */
    $confirmationTool = new RequestConfirmation();
    $agent = new MediaTrackingAgent(confirmationTool: $confirmationTool);

    $tools = collect($agent->tools());
    $this->assertTrue($tools->contains($confirmationTool));
});

test('MediaTrackingAgent tools() includes a RequestConfirmation when none injected', function () {
    /** @var TestCase $this */
    $agent = new MediaTrackingAgent();

    $tools = collect($agent->tools());
    $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof RequestConfirmation));
});
