<?php

use App\Ai\Tools\MediaWebSearchAgentTool;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Tools\Request;

describe('handle()', function () {
    test('returns error when query is empty string', function () {
        /** @var TestCase $this */
        $result = json_decode(
            (new MediaWebSearchAgentTool)->handle(new Request(['query' => ''])),
            true,
        );

        $this->assertArrayHasKey('error', $result);
    });

    test('returns error when query is not provided', function () {
        /** @var TestCase $this */
        $result = json_decode(
            (new MediaWebSearchAgentTool)->handle(new Request([])),
            true,
        );

        $this->assertArrayHasKey('error', $result);
    });
});
