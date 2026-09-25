<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * TEMPORARY, for the #218 experiment: logs the JSON-RPC method of every MCP
 * request, so the preview's logs show whether a client ever asks for
 * prompts/list. Delete before merging.
 */
class LogMcpMethod
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('mcp request', [
            'path' => $request->path(),
            'method' => $request->input('method'),
            'params' => $request->input('params.name'),
            'user_agent' => $request->userAgent(),
        ]);

        return $next($request);
    }
}
