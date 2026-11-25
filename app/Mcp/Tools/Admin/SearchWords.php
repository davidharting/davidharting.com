<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Tools\Admin\Concerns\PresentsWords;
use App\Models\Word;
use App\Queries\ListWordsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Look words up on David's vocabulary list, registered only on AdminServer.
 * Browsing is list-words, the same split as the words page's search mode.
 */
#[IsReadOnly]
#[IsIdempotent]
#[Description(<<<'TEXT'
    Search David Harting's private vocabulary list for words whose spelling
    or either definition contains the query, ignoring case. Results are
    ranked best match first: the word itself, then words starting with the
    query, then words containing it, then words found only in a definition,
    each group alphabetical. Each result says which in matched_on: "word" or
    "definition". Use this to check whether a word is already on the list,
    or to find a word's id for edit-word. To browse the list, use list-words.

    Returns each word's id, the word, its short definition, when it was
    added, its learning status and how many times David has missed it in
    practice. The markdown full definition is left out to keep results
    small; set include_full_definition to true to get it.

    Results are paginated: use page and per_page to walk through them.
    TEXT)]
class SearchWords extends Tool
{
    use PresentsWords;

    /**
     * Whether the caller is entitled to the admin surface at all. Failing this
     * hides the tool from tools/list and makes tools/call answer "Tool not
     * found": both resolve through the same filtered collection.
     *
     * handle() asks the narrower question of whether the caller may see
     * words. Neither check substitutes for the other.
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
        if ($request->user()?->cannot('viewAny', Word::class) ?? true) {
            return Response::error('You are not authorized to read David\'s words.');
        }

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
            ...$this->pageOptionsRules(),
        ]);

        $includeFullDefinition = (bool) ($validated['include_full_definition'] ?? false);

        $paginator = (new ListWordsQuery(
            search: $validated['query'],
            includeFullDefinition: $includeFullDefinition,
            includeTimesMissed: true,
        ))->paginate(
            perPage: $validated['per_page'] ?? self::DEFAULT_PER_PAGE,
            page: $validated['page'] ?? 1,
        );

        return Response::structured($this->presentPage(
            $paginator,
            fn (Word $word): array => [
                ...$this->presentWord($word, $includeFullDefinition),
                'matched_on' => $word->matches_word ? 'word' : 'definition',
            ],
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
            'query' => $schema->string()
                ->required()
                ->description('The text to look for in each word and its short and full definitions, ignoring case.'),
            ...$this->pageOptionsSchema($schema),
        ];
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, JsonSchema>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->pageOutputSchema($schema, [
            'matched_on' => $schema->string()
                ->enum(['word', 'definition'])
                ->description('Whether the query matched the word\'s spelling, or only one of its definitions.'),
        ]);
    }
}
