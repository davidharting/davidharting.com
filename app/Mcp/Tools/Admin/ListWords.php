<?php

namespace App\Mcp\Tools\Admin;

use App\Enum\LearningStatus;
use App\Enum\WordSort;
use App\Mcp\Tools\Admin\Concerns\PresentsWords;
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
 * Browse David's vocabulary list, registered only on AdminServer. Looking
 * a word up is search-words, the same split as the words page's search mode.
 */
#[IsReadOnly]
#[IsIdempotent]
#[Description(<<<'TEXT'
    Browse the words on David Harting's private vocabulary list, most
    recently added first by default. Returns each word's id, the word, its
    short definition, when it was added, its learning status and how many
    times David has missed it in practice. Pass sort as a_to_z for
    alphabetical order, or most_missed for the words he misses most first.
    To find particular words, use search-words instead.

    A word's learning status is exactly one of: new (never practised),
    mastered (he is expected to remember it three weeks or more out),
    troublesome (not mastered, and hard for him) or learning (anything
    else). Pass status to keep only words with that status.

    The markdown full definition is left out to keep results small; set
    include_full_definition to true to get it.

    Results are paginated: use page and per_page to walk through them.
    TEXT)]
class ListWords extends Tool
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
            'sort' => ['sometimes', 'string', Rule::enum(WordSort::class)->only(self::sorts())],
            'status' => ['sometimes', 'string', Rule::enum(LearningStatus::class)],
            ...$this->pageOptionsRules(),
        ]);

        $includeFullDefinition = (bool) ($validated['include_full_definition'] ?? false);

        $paginator = (new ListWordsQuery(
            sort: isset($validated['sort']) ? WordSort::from($validated['sort']) : null,
            status: isset($validated['status']) ? LearningStatus::from($validated['status']) : null,
            includeFullDefinition: $includeFullDefinition,
            includeTimesMissed: true,
        ))->paginate(
            perPage: $validated['per_page'] ?? self::DEFAULT_PER_PAGE,
            page: $validated['page'] ?? 1,
        );

        return Response::structured($this->presentPage(
            $paginator,
            fn (Word $word): array => $this->presentWord($word, $includeFullDefinition),
        ));
    }

    /**
     * The sorts for browsing. Best match ranks a search, so it is search-words'.
     *
     * @return list<WordSort>
     */
    private static function sorts(): array
    {
        return [WordSort::Recent, WordSort::AToZ, WordSort::MostMissed];
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sort' => $schema->string()
                ->enum(array_column(self::sorts(), 'value'))
                ->description('recent (the default) for most recently added first, a_to_z for alphabetical, or most_missed for the words missed most often in practice first.'),
            'status' => $schema->string()
                ->enum(array_column(LearningStatus::cases(), 'value'))
                ->description('Keep only words with this learning status: new, mastered, troublesome or learning. Leave it out for every word.'),
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
        return $this->pageOutputSchema($schema);
    }
}
