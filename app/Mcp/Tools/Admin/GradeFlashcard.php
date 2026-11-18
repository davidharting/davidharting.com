<?php

namespace App\Mcp\Tools\Admin;

use App\Enum\Recall;
use App\Models\Flashcard;
use App\Models\Word;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Record how David did on a flashcard during practice over MCP, registered
 * only on AdminServer. Every call logs a review and reschedules the card,
 * so it is neither idempotent nor undoable.
 */
#[Description(<<<'TEXT'
    Record whether David got a flashcard right, after reveal-flashcard has
    shown him the answer. This logs a review and reschedules the flashcard
    with spaced repetition, so a word he got comes back later and a word he
    missed comes back soon.

    Record only what David says: ask him "got it or missed it?" and pass
    got_it or missed_it accordingly. Don't judge his answer for him.

    Call it exactly once per turn: every call is logged as another review
    and cannot be undone, so don't retry a call that succeeded.
    TEXT)]
class GradeFlashcard extends Tool
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
        $validated = $request->validate([
            'flashcard_id' => ['required', 'integer', Rule::exists(Flashcard::class, 'id')],
            'result' => ['required', 'string', Rule::enum(Recall::class)],
        ]);

        $flashcard = Flashcard::query()->with('quizzable')->findOrFail($validated['flashcard_id']);

        if ($request->user()?->cannot('practice', $flashcard) ?? true) {
            return Response::error('You are not authorized to grade this flashcard.');
        }

        $result = Recall::from($validated['result']);
        $flashcard->grade($result->rating());

        /** @var Word $word */
        $word = $flashcard->quizzable;

        return Response::structured([
            'flashcard_id' => $flashcard->id,
            'word' => $word->word,
            'result' => $result->value,
            'next_due_at' => $flashcard->schedule->due->format(DATE_ATOM),
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
            'flashcard_id' => $schema->integer()
                ->required()
                ->description('The id of the flashcard, from next-flashcard.'),
            'result' => $schema->string()
                ->enum(array_column(Recall::cases(), 'value'))
                ->required()
                ->description('got_it when David says he knew the word, missed_it when he says he did not.'),
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
            'flashcard_id' => $schema->integer()->description('The id of the flashcard.'),
            'word' => $schema->string()->description('The word on the flashcard.'),
            'result' => $schema->string()->description('The result recorded: got_it or missed_it.'),
            'next_due_at' => $schema->string()->description('When the flashcard is next due, in ISO 8601 format.'),
        ];
    }
}
