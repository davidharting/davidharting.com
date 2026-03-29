<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\MediaWritingAgentTool;
use App\Ai\Tools\RequestConfirmation;
use App\Ai\Tools\ResolveMediaTool;
use App\Ai\Tools\SearchMedia;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;

test("uses Anthropic's Sonnet 4.6", function () {
    /** @var TestCase $this */
    $reflection = new ReflectionClass(MediaTrackingAgent::class);

    $providerAttributes = $reflection->getAttributes(Provider::class);
    $this->assertNotEmpty($providerAttributes);
    $this->assertSame('anthropic', $providerAttributes[0]->newInstance()->value);

    $modelAttributes = $reflection->getAttributes(Model::class);
    $this->assertNotEmpty($modelAttributes);
    $this->assertSame('claude-sonnet-4-6', $modelAttributes[0]->newInstance()->value);
});

test('instructions mention media tracking and library status', function () {
    /** @var TestCase $this */
    $agent = MediaTrackingAgent::make();
    $instructions = $agent->instructions();

    $this->assertStringContainsStringIgnoringCase('media', $instructions);
    $this->assertStringContainsStringIgnoringCase('status', $instructions);
});

describe('tools()', function () {
    test('includes ResolveMediaTool, SearchMedia, and RequestConfirmation by default', function () {
        /** @var TestCase $this */
        $agent = MediaTrackingAgent::make();
        $tools = collect($agent->tools());

        $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof ResolveMediaTool));
        $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof SearchMedia));
        $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof RequestConfirmation));
    });

    test('does not include WebSearch directly', function () {
        /** @var TestCase $this */
        $agent = MediaTrackingAgent::make();
        $tools = collect($agent->tools());

        $this->assertFalse($tools->contains(fn ($tool) => $tool instanceof \Laravel\Ai\Providers\Tools\WebSearch));
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

    test('does not include MediaWritingAgentTool by default', function () {
        /** @var TestCase $this */
        $agent = new MediaTrackingAgent;

        $tools = collect($agent->tools());
        $this->assertFalse($tools->contains(fn ($tool) => $tool instanceof MediaWritingAgentTool));
    });

    test('includes injected MediaWritingAgentTool when provided', function () {
        /** @var TestCase $this */
        $writingTool = new MediaWritingAgentTool;
        $agent = new MediaTrackingAgent(writingTool: $writingTool);

        $tools = collect($agent->tools());
        $this->assertTrue($tools->contains($writingTool));
    });
});
