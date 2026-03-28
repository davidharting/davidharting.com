<?php

use App\Ai\Tools\MediaWritingAgentTool;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Tools\Request;

describe('handle()', function () {
    test('returns error when plan is empty string', function () {
        /** @var TestCase $this */
        $result = json_decode(
            (new MediaWritingAgentTool)->handle(new Request(['plan' => ''])),
            true,
        );

        $this->assertArrayHasKey('error', $result);
    });

    test('returns error when plan is not provided', function () {
        /** @var TestCase $this */
        $result = json_decode(
            (new MediaWritingAgentTool)->handle(new Request([])),
            true,
        );

        $this->assertArrayHasKey('error', $result);
    });
});
