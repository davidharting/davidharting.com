<?php

namespace App\Mcp\Tools\Admin;

use App\Models\Flashcard;
use App\Models\Word;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Turn a flashcard over during practice over MCP, registered only on
 * AdminServer. Kept apart from next-flashcard so the answer only reaches
 * the agent once David has had his try.
 */
#[IsReadOnly]
#[IsIdempotent]
#[Description(<<<'TEXT'
    Turn over a flashcard from next-flashcard to see the answer: the word's
    short definition. Only call this once David has said what he thinks the
    word means, or has said he doesn't know.

    Show David the short definition, then ask him whether he got it or
    missed it, and record his answer with grade-flashcard. Set
    include_full_definition to true when he wants the whole dictionary entry.
    TEXT)]
class RevealFlashcard extends Tool
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
        // Authorized before the id is looked up, so a refused caller cannot
        // tell which flashcards exist.
        if ($request->user()?->cannot('viewAny', Flashcard::class) ?? true) {
            return Response::error('You are not authorized to see this flashcard.');
        }

        $validated = $request->validate([
            'flashcard_id' => ['required', 'integer'],
            'include_full_definition' => ['sometimes', 'boolean'],
        ]);

        $flashcard = Flashcard::query()->forWords()->with('quizzable')->find($validated['flashcard_id']);

        if ($flashcard === null) {
            return Response::error(sprintf('There is no word flashcard with flashcard_id %d.', $validated['flashcard_id']));
        }

        if ($request->user()->cannot('view', $flashcard)) {
            return Response::error('You are not authorized to see this flashcard.');
        }

        /** @var Word $word */
        $word = $flashcard->quizzable;
        $includeFullDefinition = (bool) ($validated['include_full_definition'] ?? false);

        return Response::structured([
            'flashcard_id' => $flashcard->id,
            'word' => $word->word,
            'short_definition' => $word->short_definition,
            ...($includeFullDefinition ? ['full_definition' => $word->full_definition] : []),
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
            'include_full_definition' => $schema->boolean()
                ->description('Whether to include the word\'s markdown full definition. Defaults to false.'),
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
            'word' => $schema->string()->description('The word on the front of the flashcard.'),
            'short_definition' => $schema->string()->description('The answer: plain-words senses separated by semicolons.'),
            'full_definition' => $schema->string()->nullable()->description('The markdown full definition, if any. Present only when include_full_definition is true.'),
        ];
    }
}
