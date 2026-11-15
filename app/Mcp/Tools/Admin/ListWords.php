<?php

namespace App\Mcp\Tools\Admin;

use App\Enum\WordSort;
use App\Models\Word;
use App\Queries\ListWordsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Browse David's vocabulary list, registered only on AdminServer.
 */
#[IsReadOnly]
#[IsIdempotent]
#[Description(<<<'TEXT'
    List the words on David Harting's private vocabulary list, most recently
    added first. Returns each word's id, the word, its short definition and
    when it was added. Pass search to keep only words whose spelling or
    either definition contains that text, ignoring case. Pass sort as a_to_z
    for alphabetical order.

    The markdown full definition is left out to keep results small; set
    include_full_definition to true to get it.

    Results are paginated: use page and per_page to walk through them.
    TEXT)]
class ListWords extends Tool
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 200;

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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', Rule::enum(WordSort::class)],
            'include_full_definition' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $options = $this->options($validated);

        $paginator = (new ListWordsQuery(
            search: $options['search'],
            sort: $options['sort'],
            includeFullDefinition: $options['includeFullDefinition'],
        ))->paginate(
            perPage: $options['perPage'],
            page: $options['page'],
        );

        return Response::structured([
            'words' => collect($paginator->items())->map(fn (Word $word): array => [
                'word_id' => $word->id,
                'word' => $word->word,
                'short_definition' => $word->short_definition,
                'added_at' => $word->created_at->toIso8601String(),
                ...($options['includeFullDefinition'] ? ['full_definition' => $word->full_definition] : []),
            ])->all(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ]);
    }

    /**
     * Every listing option with its default filled in, as the query and
     * the response use it.
     *
     * @param  array<string, mixed>  $validated
     * @return array{search: ?string, sort: WordSort, includeFullDefinition: bool, page: int, perPage: int}
     */
    private function options(array $validated): array
    {
        return [
            'search' => $validated['search'] ?? null,
            'sort' => isset($validated['sort']) ? WordSort::from($validated['sort']) : WordSort::Recent,
            'includeFullDefinition' => (bool) ($validated['include_full_definition'] ?? false),
            'page' => $validated['page'] ?? 1,
            'perPage' => $validated['per_page'] ?? self::DEFAULT_PER_PAGE,
        ];
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Text to look for in each word and its short and full definitions, ignoring case.'),
            'sort' => $schema->string()
                ->enum(array_column(WordSort::cases(), 'value'))
                ->description('recent (the default) for most recently added first, or a_to_z for alphabetical.'),
            'include_full_definition' => $schema->boolean()
                ->description('Whether to include each word\'s markdown full definition. Defaults to false.'),
            'page' => $schema->integer()
                ->min(1)
                ->description('The page of results to return. Defaults to 1.'),
            'per_page' => $schema->integer()
                ->min(1)
                ->max(self::MAX_PER_PAGE)
                ->description(sprintf(
                    'How many words to return per page. Defaults to %d, maximum %d.',
                    self::DEFAULT_PER_PAGE,
                    self::MAX_PER_PAGE,
                )),
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
            'words' => $schema->array()
                ->items($schema->object([
                    'word_id' => $schema->integer()->description('The id of the word, for edit-word.'),
                    'word' => $schema->string()->description('The word.'),
                    'short_definition' => $schema->string()->description('The short definition: plain-words senses separated by semicolons.'),
                    'added_at' => $schema->string()->description('When the word was added, in ISO 8601 format.'),
                    'full_definition' => $schema->string()->nullable()->description('The markdown full definition, if any. Present only when include_full_definition is true.'),
                ]))
                ->description('The words on this page, in the requested order.'),
            'total' => $schema->integer()->description('The total number of matching words across all pages.'),
            'total_pages' => $schema->integer()->description('The number of pages of matching words at this per_page.'),
            'page' => $schema->integer()->description('The current page number.'),
            'per_page' => $schema->integer()->description('The number of words per page.'),
            'has_more_pages' => $schema->boolean()->description('Whether more pages of words are available.'),
        ];
    }
}
