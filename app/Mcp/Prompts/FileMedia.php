<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/**
 * Walk one task from David's Todoist "File" project into the media log,
 * registered only on AdminServer.
 *
 * The Todoist half relies on the client also having a Todoist connector; this
 * server only supplies the media tools.
 */
#[Description('File one media item from the Todoist "🗄️ File" project into the media log: propose the calls, wait for a go-ahead, write, verify, then complete the task.')]
class FileMedia extends Prompt
{
    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->can('administrate') ?? false;
    }

    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): Response
    {
        $request->validate(['item' => 'nullable|string']);

        $item = trim((string) $request->get('item'));

        $pickTheTask = $item === ''
            ? 'Take the oldest task in the File project.'
            : "Find the task matching \"{$item}\".";

        return Response::text(<<<MARKDOWN
            File one media item from my Todoist "🗄️ File" project into my media log.

            1. **Pick the task.** {$pickTheTask} Read its title, description,
               comments and addedAt timestamp.

            2. **Work out the intent.** Is this started, finished, abandoned, a
               comment, or just "add to backlog"? Resolve relative dates ("today",
               "yesterday", "2 days ago") against addedAt converted to
               America/Indiana/Indianapolis — not the UTC date. If the date is
               genuinely unknown, say so rather than guess.

            3. **Check the log.** Use query-media with history to find the item.
               Note near-matches, its current status, and any existing events of
               the same type. If it isn't there, identify the proper title,
               creator (director / developer / author / showrunner) and release
               year for create-media.

            4. **Propose, then stop.** Tell me which task you picked, which item
               it maps to (and why, if there were other candidates), the exact
               tool calls you'd make, and anything you're unsure of or leaving out
               (e.g. no start date). Put my notes on the event's comment, not the
               item's remark. Wait for my go-ahead.

            5. **Write and verify.** Make the calls, then re-query the item and
               confirm status, dates and comment match what we agreed.

            6. **Close the loop.** Only once the read-back is correct, complete
               the Todoist task. If anything failed or looks off, leave the task
               open and tell me.
            MARKDOWN);
    }

    /**
     * Get the prompt's arguments.
     *
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'item',
                description: 'Which task to file, e.g. "Mean Streets". Leave empty to take the oldest task in the File project.',
            ),
        ];
    }
}
