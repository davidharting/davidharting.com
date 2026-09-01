<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Enums\RequestHeader;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// expect()->extend('toBeOne', function () {
//    return $this->toBe(1);
// });

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * POST a JSON-RPC request to an MCP server over the streamable HTTP transport.
 *
 * Since MCP 2026-07-28 the transport rejects any request whose Mcp-Protocol-Version,
 * Mcp-Method, and Mcp-Name headers do not mirror the JSON-RPC body — header-based
 * routing, so intermediaries can route without parsing the body. Building those by
 * hand in every test is noise, and getting one wrong yields a -32020 header mismatch
 * rather than the assertion you meant to make.
 *
 * The revision also made the protocol stateless, so what the `initialize` handshake used
 * to establish once per session now rides in `params._meta` on every single request.
 *
 * @param  array<string, mixed>  $params
 */
function postMcp(string $uri, string $method, array $params = [], int|string $id = 1): TestResponse
{
    $version = ProtocolVersion::LATEST->value;

    $headers = [
        RequestHeader::PROTOCOL_VERSION->value => $version,
        RequestHeader::METHOD->value => $method,
    ];

    $nameKey = match ($method) {
        'tools/call', 'prompts/get' => 'name',
        'resources/read' => 'uri',
        default => null,
    };

    if ($nameKey !== null && isset($params[$nameKey]) && is_string($params[$nameKey])) {
        $headers[RequestHeader::NAME->value] = $params[$nameKey];
    }

    return test()->postJson($uri, [
        'jsonrpc' => '2.0',
        'id' => $id,
        'method' => $method,
        'params' => [
            ...$params,
            '_meta' => [
                MetaKey::PROTOCOL_VERSION->value => $version,
                MetaKey::CLIENT_CAPABILITIES->value => (object) [],
            ],
        ],
    ], $headers);
}
