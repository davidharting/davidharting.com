<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Tools\Admin\Concerns\DescribesWords;
use App\Models\Word;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * Correct a word on David's vocabulary list, registered only on AdminServer.
 *
 * Omitting a field leaves it untouched, as in edit-media: the caller is a
 * language model, and reading omission as "clear" would let one fixing a
 * typo wipe the senses. Senses are replaced as a whole list, since a sense
 * has no identity of its own to edit by.
 */
#[IsIdempotent]
#[IsDestructive]
#[Description(<<<'TEXT'
    Correct a word on David Harting's private vocabulary list: its spelling,
    its short definition, or the dictionary senses he is learning it in. Get
    the word_id from add-word, which returns an existing word unchanged.

    Only the fields you pass are changed. senses replaces the whole list, so
    pass every sense that should remain. To clear the short definition pass
    null.

    Words are compared case-insensitively. Respelling a word onto another word
    already on the list is refused and names that word's id; nothing is
    changed.
    TEXT)]
class EditWord extends Tool
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
        $validated = $request->validate([
            'word_id' => ['required', 'integer', Rule::exists(Word::class, 'id')],
            'word' => ['sometimes', 'string', 'filled', 'max:255'],
            'short_definition' => ['sometimes', 'nullable', 'string'],
            ...$this->sensesRules(),
        ]);

        $word = Word::findOrFail($validated['word_id']);

        if ($request->user()?->cannot('update', $word) ?? true) {
            return Response::error('You are not authorized to edit this word.');
        }

        if (array_keys($validated) === ['word_id']) {
            return Response::error('Nothing to change: pass at least one field to edit alongside word_id.');
        }

        $conflict = isset($validated['word']) ? Word::spelledAs($validated['word'], $word) : null;

        if ($conflict !== null) {
            return $this->conflictError($conflict);
        }

        try {
            $word->update($validated);
        } catch (UniqueConstraintViolationException) {
            // A concurrent call took the spelling after the check above.
            return $this->conflictError(Word::spelledAs($validated['word'], $word));
        }

        return Response::structured($this->wordResult($word));
    }

    private function conflictError(Word $conflict): Response
    {
        return Response::error(sprintf(
            'Refused: "%s" is already on the list as word_id %d. Nothing was changed.',
            $conflict->word,
            $conflict->id,
        ));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'word_id' => $schema->integer()
                ->required()
                ->description('The id of the word to change.'),
            'word' => $schema->string()
                ->description('The corrected spelling, stored as written.'),
            'short_definition' => $schema->string()
                ->nullable()
                ->description('A short definition in plain words. Pass null to clear it.'),
            'senses' => $this->sensesSchema($schema)
                ->description('The complete list of senses to keep, replacing what is stored.'),
        ];
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, JsonSchema>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->wordOutputSchema($schema);
    }
}
