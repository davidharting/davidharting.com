<?php

namespace App\Mcp\Tools\Admin;

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
 * never quietly overwrites a definition David already has. Changing a stored
 * word is edit-word's job.
 */
#[IsIdempotent]
#[IsDestructive(false)]
#[Description(<<<'TEXT'
    Add a word to David Harting's private vocabulary list, with a short
    definition to practise against.

    The short definition is brief and in plain words, and may be your own.
    When David cares about more than one sense of the word, give a brief
    definition of each, separated by semicolons, e.g. "lasting a very short
    time; a plant that lives only a short time". Base it on a real
    dictionary rather than guessing.

    full_definition is optional markdown holding the whole dictionary entry:
    every sense with its part of speech and example uses. David can expand
    it while practising.

    Words are matched case-insensitively. When the word is already on the list
    nothing is changed: the response says word_created=false and returns the
    word as stored. Use edit-word with its word_id to change it.
    TEXT)]
class AddWord extends Tool
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
        if ($request->user()?->cannot('create', Word::class) ?? true) {
            return Response::error('You are not authorized to add words.');
        }

        $validated = $request->validate([
            'word' => ['required', 'string', 'filled', 'max:255'],
            'short_definition' => ['required', 'string', 'filled'],
            'full_definition' => ['sometimes', 'nullable', 'string'],
        ]);

        try {
            $word = Word::spelledAs($validated['word']) ?? Word::create($validated);
        } catch (UniqueConstraintViolationException) {
            // A concurrent call added it between the lookup and the insert.
            $word = Word::spelledAs($validated['word']);
        }

        return Response::structured([
            'word_id' => $word->id,
            'word' => $word->word,
            'short_definition' => $word->short_definition,
            'full_definition' => $word->full_definition,
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
                ->required()
                ->description('Brief plain-words definitions of the senses David cares about, separated by semicolons.'),
            'full_definition' => $schema->string()
                ->description('Optional markdown with the whole dictionary entry: every sense, its part of speech and example uses.'),
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
            'word_id' => $schema->integer()->description('The internal id of the word, for edit-word.'),
            'word' => $schema->string()->description('The word as stored.'),
            'short_definition' => $schema->string()->description('The short definition as stored.'),
            'full_definition' => $schema->string()->nullable()->description('The markdown full definition as stored, if any.'),
            'word_created' => $schema->boolean()->description('True when this call added the word; false when it was already on the list and nothing was changed.'),
        ];
    }
}
