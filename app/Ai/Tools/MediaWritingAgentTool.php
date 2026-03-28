<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

use function Laravel\Ai\agent;

class MediaWritingAgentTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Execute a confirmed media tracking plan. Call this after the user confirms, passing your exact plan text.';
    }

    public function handle(Request $request): Stringable|string
    {
        $plan =  $request->string('plan', '');

        if ($plan === '') {
            return json_encode(
                ['error' => 'plan must not be empty. Pass the exact plan text you stated in the confirmation message.'],
                JSON_THROW_ON_ERROR,
            );
        }

        Log::info('MediaWritingAgentTool called', ['plan' => $plan]);

        $response = agent(
            instructions: $this->instructions(),
            tools: [new SearchMedia, new CreateMedia, new CreateMediaEvent],
        )->prompt($plan, provider: 'anthropic', model: 'claude-sonnet-4-6');

        return $response->text;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan' => $schema->string()->required()
                ->description('The confirmed plan, e.g. "Add The Hobbit (1937) by J.R.R. Tolkien — Book and log a started event."'),
        ];
    }

    private function instructions(): string
    {
        $today = now()->toDateString();

        return <<<PROMPT
        You execute a confirmed media tracking plan by calling the available DB tools.

        Today's date is {$today}.

        **Creator resolution**
        Always call SearchMedia with the creator name first to get creator_id before calling CreateMedia.
        Use partial search — for example, to find J.R.R. Tolkien search for "Tolkien" and inspect the results.
        If a matching creator is found, pass creator_id to CreateMedia.
        If not found, pass creator_name — the tool will create the creator.

        **Backlog only**
        If the plan is to add to the library with no event, call CreateMedia only.

        **Return value**
        Return a concise plain-text summary of exactly what was written.
        Example: "Added The Hobbit (1937) by J.R.R. Tolkien — Book. Logged a started event on March 27, 2026."
        PROMPT;
    }
}
