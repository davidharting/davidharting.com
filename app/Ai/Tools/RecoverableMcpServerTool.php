<?php

namespace App\Ai\Tools;

use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\McpServerTool;
use Laravel\Ai\Tools\Request;

/**
 * Adapts an MCP server tool for agent use with recoverable validation errors.
 *
 * laravel/ai's stock McpServerTool wrapper lets a ValidationException thrown
 * by $request->validate() escape, and the agent loop does not catch tool
 * exceptions, so one invalid argument from the model would abort the entire
 * run. This subclass converts the failure into a JSON error string the model
 * can read and correct on its next step.
 */
class RecoverableMcpServerTool extends McpServerTool
{
    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            return parent::handle($request);
        } catch (ValidationException $e) {
            return json_encode(
                ['error' => implode(' ', $e->validator->errors()->all())],
                JSON_THROW_ON_ERROR,
            );
        }
    }
}
