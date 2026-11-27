<?php

namespace App\Queries;

use App\Fsrs\Rating;
use App\Models\Word;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Look words up on David's vocabulary list. A search is a lookup, so it is
 * always ranked by best match: the word itself, then words starting with
 * the search, then words containing it, then words found only in a
 * definition, each group alphabetical. Browsing is ListWordsQuery.
 */
class SearchWordsQuery
{
    /**
     * The `match_rank` of a word found only in a definition.
     */
    private const int DEFINITION_ONLY_RANK = 3;

    /**
     * This query performs no authorization. The caller is responsible for
     * checking the viewer may see David's words at all.
     *
     * @param  string  $search  Matched case-insensitively against the word and
     *                          both of its definitions.
     * @param  bool  $includeFullDefinition  Whether to load `full_definition`.
     * @param  bool  $includeTimesMissed  Whether to count each word's misses,
     *                                    as `times_missed`.
     */
    public function __construct(
        public string $search,
        public bool $includeFullDefinition = false,
        public bool $includeTimesMissed = false,
    ) {}

    /**
     * Each word found comes with its flashcard loaded, for its learning
     * status, and with `matches_word`: whether its spelling contains the
     * search, rather than only a definition.
     *
     * @return LengthAwarePaginator<int, Word>
     */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        $search = trim($this->search);
        $contains = '%'.LikePattern::escape($search).'%';
        $definitionOnly = self::DEFINITION_ONLY_RANK;

        $words = Word::query()
            ->select(['words.id', 'words.word', 'words.short_definition', 'words.created_at'])
            ->selectRaw(
                "case when lower(words.word) = lower(?) then 0 when words.word ilike ? then 1 when words.word ilike ? then 2 else {$definitionOnly} end as match_rank",
                [$search, LikePattern::escape($search).'%', $contains],
            )
            ->with('flashcard')
            ->where(function (Builder $query) use ($contains): void {
                $query->whereLike('words.word', $contains)
                    ->orWhereLike('words.short_definition', $contains)
                    ->orWhereLike('words.full_definition', $contains);
            })
            ->orderBy('match_rank', 'asc')
            ->orderByRaw('lower(words.word) asc')
            ->orderBy('words.id', 'asc');

        if ($this->includeFullDefinition) {
            $words->addSelect('words.full_definition');
        }

        if ($this->includeTimesMissed) {
            $words->withCount(['reviews as times_missed' => fn (Builder $reviews) => $reviews->where('rating', Rating::Again)]);
        }

        return $words->paginate(perPage: $perPage, page: $page)
            ->through(fn (Word $word): Word => $word->setAttribute('matches_word', $word->match_rank < self::DEFINITION_ONLY_RANK));
    }
}
