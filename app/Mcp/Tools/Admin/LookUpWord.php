<?php

namespace App\Mcp\Tools\Admin;

use App\Clients\MerriamWebster\Entry;
use App\Clients\MerriamWebster\MerriamWebster;
use App\Clients\MerriamWebster\MerriamWebsterFailed;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Look a word up in Merriam-Webster, registered only on AdminServer.
 *
 * Returns each entry's typed fields, not M-W's raw JSON: the nested
 * definition structure is large and full of inline markup an agent doesn't
 * need to write a short definition.
 *
 * handle() checks the administrate gate itself rather than a policy, since
 * a lookup reads no model.
 */
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
#[Description(<<<'TEXT'
    Look a word up in Merriam-Webster's Collegiate Dictionary before adding
    it to David Harting's vocabulary list with add-word.

    Returns one entry per headword and part of speech: "bow" the verb and
    "bow" the weapon are separate entries. Each entry gives the headword,
    its part of speech and plain-text definitions of its first few senses.
    The search also returns related headwords, such as "rainbow" for "bow",
    so check each entry's headword. When the word isn't in the dictionary,
    found is false and suggestions lists close spellings.

    Use the definitions to write add-word's short definition in brief, plain
    words of your own. Don't copy Merriam-Webster's wording into David's
    list wholesale.
    TEXT)]
class LookUpWord extends Tool
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
    public function handle(Request $request, MerriamWebster $dictionary): Response|ResponseFactory
    {
        if ($request->user()?->cannot('administrate') ?? true) {
            return Response::error('You are not authorized to look up words.');
        }

        $validated = $request->validate([
            'word' => ['required', 'string', 'filled', 'max:100'],
        ]);

        try {
            $lookup = $dictionary->lookUp($validated['word']);
        } catch (MerriamWebsterFailed $failure) {
            report($failure);

            return Response::error('The dictionary lookup failed. Try again later.');
        }

        return Response::structured([
            'word' => $lookup->term,
            'source' => "Merriam-Webster's Collegiate Dictionary",
            'found' => $lookup->isFound(),
            'entries' => array_map(fn (Entry $entry): array => [
                'headword' => $entry->headword,
                'homograph' => $entry->homograph,
                'part_of_speech' => $entry->partOfSpeech,
                'short_definitions' => $entry->shortDefinitions,
                'is_offensive' => $entry->isOffensive,
            ], $lookup->entries),
            'suggestions' => $lookup->suggestions,
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
                ->description('The word or phrase to look up.'),
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
            'word' => $schema->string()->description('The word as looked up, trimmed.'),
            'source' => $schema->string()->description('The dictionary the definitions come from.'),
            'found' => $schema->boolean()->description('True when the dictionary has at least one entry for the word.'),
            'entries' => $schema->array()->items($schema->object([
                'headword' => $schema->string()->description('The entry\'s headword, which may differ from the word looked up.'),
                'homograph' => $schema->integer()->nullable()->description('Which of several same-spelled headwords this is, when there are several.'),
                'part_of_speech' => $schema->string()->nullable()->description('For example noun, verb or adjective.'),
                'short_definitions' => $schema->array()->items($schema->string())->description('Plain-text definitions of the entry\'s first few senses.'),
                'is_offensive' => $schema->boolean()->description('True when Merriam-Webster labels the entry offensive.'),
            ]))->description('One entry per headword and part of speech.'),
            'suggestions' => $schema->array()->items($schema->string())->description('Close spellings, when the word was not found.'),
        ];
    }
}
