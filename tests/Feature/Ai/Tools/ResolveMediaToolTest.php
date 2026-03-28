<?php

use App\Ai\Tools\ResolveMediaTool;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

describe('handle()', function () {
    test('returns error when reference is empty string', function () {
        /** @var TestCase $this */
        $result = json_decode(
            (new ResolveMediaTool)->handle(new Request(['reference' => ''])),
            true,
        );

        $this->assertArrayHasKey('error', $result);
    });

    test('returns error when reference is not provided', function () {
        /** @var TestCase $this */
        $result = json_decode(
            (new ResolveMediaTool)->handle(new Request([])),
            true,
        );

        $this->assertArrayHasKey('error', $result);
    });
});

describe('description()', function () {
    test('is not empty', function () {
        /** @var TestCase $this */
        $this->assertNotEmpty((new ResolveMediaTool)->description());
    });
});

describe('schema()', function () {
    test('defines reference field', function () {
        /** @var TestCase $this */
        $schema = (new ResolveMediaTool)->schema(new JsonSchemaTypeFactory);

        $this->assertArrayHasKey('reference', $schema);
    });
});
