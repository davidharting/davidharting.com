<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Agents\MediaWebSearchAgent;
use App\Ai\Agents\MediaWritingAgent;
use App\Ai\Tools\RequestConfirmation;
use App\Ai\Tools\SearchMedia;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Tools\WebSearch;

test("uses Anthropic's Sonnet 4.6", function () {
    /** @var TestCase $this */
    $reflection = new ReflectionClass(MediaTrackingAgent::class);

    $providerAttributes = $reflection->getAttributes(Provider::class);
    $this->assertNotEmpty($providerAttributes);
    $this->assertSame(Lab::Anthropic, $providerAttributes[0]->newInstance()->value);

    $modelAttributes = $reflection->getAttributes(Model::class);
    $this->assertNotEmpty($modelAttributes);
    $this->assertSame('claude-sonnet-4-6', $modelAttributes[0]->newInstance()->value);
});

test('caps the tool-calling loop with a MaxSteps guardrail', function () {
    /** @var TestCase $this */
    $reflection = new ReflectionClass(MediaTrackingAgent::class);

    $maxStepsAttributes = $reflection->getAttributes(MaxSteps::class);
    $this->assertNotEmpty($maxStepsAttributes);
    $this->assertGreaterThan(0, $maxStepsAttributes[0]->newInstance()->value);
});

test('instructions mention media tracking and library status', function () {
    /** @var TestCase $this */
    $agent = MediaTrackingAgent::make();
    $instructions = $agent->instructions();

    $this->assertStringContainsStringIgnoringCase('media', $instructions);
    $this->assertStringContainsStringIgnoringCase('status', $instructions);
});

test('instructions include the current date', function () {
    /** @var TestCase $this */
    $agent = MediaTrackingAgent::make();
    $instructions = (string) $agent->instructions();

    $this->assertStringContainsString(now()->toDateString(), $instructions);
});

describe('tools()', function () {
    test('includes MediaWebSearchAgent, SearchMedia, and RequestConfirmation by default', function () {
        /** @var TestCase $this */
        $agent = MediaTrackingAgent::make();
        $tools = collect($agent->tools());

        $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof MediaWebSearchAgent));
        $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof SearchMedia));
        $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof RequestConfirmation));
    });

    // At time of writing, the Anthropic provider returns 400s when an agent mixes custom tools with
    // provider tools (like WebSearch) across multi-turn conversations. WebSearch lives inside the
    // MediaWebSearchAgent sub-agent so the orchestrator only owns custom tools.
    test('does not expose the WebSearch provider tool directly', function () {
        /** @var TestCase $this */
        $agent = MediaTrackingAgent::make();
        $tools = collect($agent->tools());

        $this->assertFalse($tools->contains(fn ($tool) => $tool instanceof WebSearch));
    });

    test('includes injected RequestConfirmation instance', function () {
        /** @var TestCase $this */
        $confirmationTool = new RequestConfirmation;
        $agent = new MediaTrackingAgent(confirmationTool: $confirmationTool);

        $tools = collect($agent->tools());
        $this->assertTrue($tools->contains($confirmationTool));
    });

    test('includes a RequestConfirmation when none injected', function () {
        /** @var TestCase $this */
        $agent = new MediaTrackingAgent;

        $tools = collect($agent->tools());
        $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof RequestConfirmation));
    });

    test('does not include MediaWritingAgent by default', function () {
        /** @var TestCase $this */
        $agent = new MediaTrackingAgent;

        $tools = collect($agent->tools());
        $this->assertFalse($tools->contains(fn ($tool) => $tool instanceof MediaWritingAgent));
    });

    test('includes MediaWritingAgent when canWrite is true', function () {
        /** @var TestCase $this */
        $agent = new MediaTrackingAgent(canWrite: true);

        $tools = collect($agent->tools());
        $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof MediaWritingAgent));
    });
});
