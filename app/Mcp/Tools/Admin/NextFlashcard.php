<?php

namespace App\Mcp\Tools\Admin;

use App\Models\Flashcard;
use App\Models\Word;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The first step of practising David's words over MCP, registered only on
 * AdminServer. It returns the word alone: the definition stays behind
 * reveal-flashcard, so an agent cannot show the answer before David has
 * tried to recall it.
 */
#[IsReadOnly]
#[IsIdempotent]
#[Description(<<<'TEXT'
    Start or continue a practice session on David Harting's private
    vocabulary list. Returns the next word flashcard to practise, most
    overdue first, and how many are due now. The definition is deliberately
    left out.

    A practice turn goes: call next-flashcard and show David only the word;
    ask him what it means and wait for his answer; call reveal-flashcard and
    show him the definition; ask him whether he got it or missed it; record
    his answer with grade-flashcard. Then call next-flashcard again.

    When nothing is due, flashcard is null and next_due_at says when the
    next one falls due. Practising before then is allowed but less useful:
    only set include_not_due to true when David asks to keep going.
    TEXT)]
class NextFlashcard extends Tool
{
    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found": both resolve through the same filtered collection.
     */
    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->can('administrate') ?? false;
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        if ($request->user()?->cannot('viewAny', Flashcard::class) ?? true) {
            return Response::error('You are not authorized to practise David\'s flashcards.');
        }

        $validated = $request->validate([
            'include_not_due' => ['sometimes', 'boolean'],
        ]);

        $includeNotDue = (bool) ($validated['include_not_due'] ?? false);

        $flashcard = Flashcard::query()
            ->forWords()
            ->unless($includeNotDue, fn (Builder $query) => $query->dueNow())
            ->nextUp()
            ->with('quizzable')
            ->first();

        /** @var Word|null $word */
        $word = $flashcard?->quizzable;

        return Response::structured([
            'flashcard' => $flashcard === null ? null : [
                'flashcard_id' => $flashcard->id,
                'word' => $word->word,
                'is_due' => $flashcard->schedule->due <= now(),
            ],
            'due_count' => Flashcard::query()->forWords()->dueNow()->count(),
            'next_due_at' => Flashcard::query()->forWords()->nextUp()->first()?->schedule->due->format(DATE_ATOM),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_not_due' => $schema->boolean()
                ->description('Whether to return the next flashcard even when it is not due yet. Defaults to false.'),
        ];
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, JsonSchema>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'flashcard' => $schema->object([
                'flashcard_id' => $schema->integer()->description('The id of the flashcard, for reveal-flashcard and grade-flashcard.'),
                'word' => $schema->string()->description('The word to show David.'),
                'is_due' => $schema->boolean()->description('Whether the flashcard is due now. False only when include_not_due is true.'),
            ])->nullable()->description('The next flashcard to practise, or null when nothing is due.'),
            'due_count' => $schema->integer()->description('How many word flashcards are due now.'),
            'next_due_at' => $schema->string()->nullable()->description('When the soonest flashcard is or was due, in ISO 8601 format; null when there are no words.'),
        ];
    }
}
