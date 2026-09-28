<?php

namespace Tests\Support;

/**
 * JSON-RPC request bodies for exercising an MCP server over HTTP.
 *
 * Posting these through the real routes, rather than calling
 * Server::tool(), runs the middleware a client actually meets.
 */
class McpRequest
{
    /**
     * The initialize handshake, for asserting what a server tells a
     * connecting client about itself.
     *
     * @return array<string, mixed>
     */
    public static function initialize(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'pest', 'version' => '1.0.0'],
            ],
        ];
    }
}
