<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RequestConfirmation implements Tool
{
    private bool $requested = false;

    public function wasRequested(): bool
    {
        return $this->requested;
    }

    public function description(): Stringable|string
    {
        return 'Call this tool when you have identified the media item and resolved all ambiguity. '
            . 'It signals that you are ready to present a confirmation plan. '
            . 'Do not call it until you are confident about the title, year, creator, and intended action.';
    }

    public function handle(Request $request): Stringable|string
    {
        $this->requested = true;

        return 'Confirmation signalled. Write your confirmation message as your response text.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
