<?php

namespace App\Mcp\Tools\Admin\Concerns;

use App\Enum\LearningStatus;
use App\Models\Word;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The shape list-words and search-words both give a page of words, so an
 * agent reads the same fields from either.
 */
trait PresentsWords
{
    private const int DEFAULT_PER_PAGE = 25;

    private const int MAX_PER_PAGE = 200;

    /**
     * A page of words, each presented by $presentWord.
     *
     * @param  LengthAwarePaginator<int, Word>  $paginator
     * @param  callable(Word): array<string, mixed>  $presentWord
     * @return array<string, mixed>
     */
    private function presentPage(LengthAwarePaginator $paginator, callable $presentWord): array
    {
        return [
            'words' => collect($paginator->items())->map($presentWord)->all(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * What every listed word says about itself. Needs the flashcard loaded
     * and `times_missed` counted.
     *
     * @return array<string, mixed>
     */
    private function presentWord(Word $word, bool $includeFullDefinition): array
    {
        return [
            'word_id' => $word->id,
            'word' => $word->word,
            'short_definition' => $word->short_definition,
            'added_at' => $word->created_at->toIso8601String(),
            'status' => $word->flashcard->learning_status->value,
            'times_missed' => $word->times_missed,
            ...($includeFullDefinition ? ['full_definition' => $word->full_definition] : []),
        ];
    }

    /**
     * The input options both tools share.
     *
     * @return array<string, JsonSchema>
     */
    private function pageOptionsSchema(JsonSchema $schema): array
    {
        return [
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
     * Validation rules for the shared input options.
     *
     * @return array<string, list<string>>
     */
    private function pageOptionsRules(): array
    {
        return [
            'include_full_definition' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * The output schema of a page of words, each with $extraWordFields too.
     *
     * @param  array<string, JsonSchema>  $extraWordFields
     * @return array<string, JsonSchema>
     */
    private function pageOutputSchema(JsonSchema $schema, array $extraWordFields = []): array
    {
        return [
            'words' => $schema->array()
                ->items($schema->object([
                    'word_id' => $schema->integer()->description('The id of the word, for edit-word.'),
                    'word' => $schema->string()->description('The word.'),
                    'short_definition' => $schema->string()->description('The short definition: plain-words senses separated by semicolons.'),
                    'added_at' => $schema->string()->description('When the word was added, in ISO 8601 format.'),
                    'status' => $schema->string()
                        ->enum(array_column(LearningStatus::cases(), 'value'))
                        ->description('How well David knows the word: new, mastered, troublesome or learning.'),
                    'times_missed' => $schema->integer()->description('How many times David has missed the word in practice.'),
                    ...$extraWordFields,
                    'full_definition' => $schema->string()->nullable()->description('The markdown full definition, if any. Present only when include_full_definition is true.'),
                ]))
                ->description('The words on this page, in order.'),
            'total' => $schema->integer()->description('The total number of words across all pages.'),
            'total_pages' => $schema->integer()->description('The number of pages of words at this per_page.'),
            'page' => $schema->integer()->description('The current page number.'),
            'per_page' => $schema->integer()->description('The number of words per page.'),
            'has_more_pages' => $schema->boolean()->description('Whether more pages of words are available.'),
        ];
    }
}
