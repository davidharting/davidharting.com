<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Tools\Admin\Concerns\DescribesWords;
use App\Models\Word;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\UniqueConstraintViolationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * Record a word David is learning, registered only on AdminServer.
 *
 * Like create-media, it finds before it creates: a word already recorded in
 * any case is returned untouched, so a retried call never duplicates one and
 * never quietly overwrites senses David already chose. Changing a stored word
 * is edit-word's job.
 */
#[IsIdempotent]
#[IsDestructive(false)]
#[Description(<<<'TEXT'
    Add a word to David Harting's private vocabulary list: a word he wants to
    learn, the dictionary senses he is learning it in, and optionally a short
    definition in plain words.

    Each sense has a part of speech, a definition and example sentences. Copy
    them from a real dictionary rather than inventing them; a short definition
    can be in your own words.

    Words are matched case-insensitively. When the word is already on the list
    nothing is changed: the response says word_created=false and returns the
    word as stored. Use edit-word with its word_id to change it.
    TEXT)]
class AddWord extends Tool
{
    use DescribesWords;

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
        if ($request->user()?->cannot('create', Word::class) ?? true) {
            return Response::error('You are not authorized to add words.');
        }

        $validated = $request->validate([
            'word' => ['required', 'string', 'filled', 'max:255'],
            'short_definition' => ['sometimes', 'nullable', 'string'],
            ...$this->sensesRules(),
        ]);

        try {
            $word = Word::spelledAs($validated['word']) ?? Word::create($validated);
        } catch (UniqueConstraintViolationException) {
            // A concurrent call added it between the lookup and the insert.
            $word = Word::spelledAs($validated['word']);
        }

        return Response::structured([
            ...$this->wordResult($word),
            'word_created' => $word->wasRecentlyCreated,
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
            'word' => $schema->string()
                ->required()
                ->description('The word, stored as written when it is new. Use lower case unless it is a proper noun.'),
            'short_definition' => $schema->string()
                ->description('A short definition in plain words, which may be your own.'),
            'senses' => $this->sensesSchema($schema)
                ->description('The dictionary senses David is learning the word in, usually one or two.'),
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
            ...$this->wordOutputSchema($schema),
            'word_created' => $schema->boolean()->description('True when this call added the word; false when it was already on the list and nothing was changed.'),
        ];
    }
}
