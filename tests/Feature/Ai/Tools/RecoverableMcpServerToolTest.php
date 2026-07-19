<?php

use App\Ai\Tools\RecoverableMcpServerTool;
use App\Mcp\Tools\QueryMedia;
use App\Models\Creator;
use App\Models\Media;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Tools\Request;

describe('handle()', function () {
    test('returns a model-readable error instead of throwing when validation fails', function () {
        /** @var TestCase $this */
        $tool = new RecoverableMcpServerTool(new QueryMedia);

        $result = json_decode($tool->handle(new Request(['media_type' => 'podcast'])), true);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('media type', $result['error']);
    });

    test('passes valid requests through to the wrapped tool as JSON', function () {
        /** @var TestCase $this */
        $creator = Creator::factory()->create(['name' => 'Frank Herbert']);
        $media = Media::factory()->book()->create(['title' => 'Dune', 'creator_id' => $creator->id]);

        $tool = new RecoverableMcpServerTool(new QueryMedia);

        $result = json_decode($tool->handle(new Request(['title' => 'dune'])), true);

        $this->assertSame(1, $result['total']);
        $this->assertSame($media->id, $result['results'][0]['media_id']);
        $this->assertSame($creator->id, $result['results'][0]['creator_id']);
    });
});

describe('name()', function () {
    test('exposes the wrapped MCP tool name to the agent', function () {
        /** @var TestCase $this */
        $tool = new RecoverableMcpServerTool(new QueryMedia);

        $this->assertSame('query-media', $tool->name());
    });
});
