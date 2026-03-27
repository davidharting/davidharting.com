<?php

use App\Ai\Tools\RequestConfirmation;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

// RequestConfirmation::handle() logs via the Log facade, so we need the app booted.
uses(Tests\TestCase::class);

test('wasRequested() returns false before handle() is called', function () {
    /** @var TestCase $this */
    $tool = new RequestConfirmation;

    $this->assertFalse($tool->wasRequested());
});

test('wasRequested() returns true after handle() is called', function () {
    /** @var TestCase $this */
    $tool = new RequestConfirmation;
    $tool->handle(new Request([]));

    $this->assertTrue($tool->wasRequested());
});

test('handle() returns a non-empty acknowledgement string', function () {
    /** @var TestCase $this */
    $tool = new RequestConfirmation;
    $result = $tool->handle(new Request([]));

    $this->assertNotEmpty((string) $result);
});

test('schema() returns an empty array', function () {
    /** @var TestCase $this */
    $tool = new RequestConfirmation;
    $schema = $tool->schema(new JsonSchemaTypeFactory);

    $this->assertSame([], $schema);
});
