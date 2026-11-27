<?php

namespace App\Queries;

use App\Enum\LearningStatus;
use App\Enum\WordSort;
use App\Fsrs\Rating;
use App\Models\Flashcard;
use App\Models\Word;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Browse David's vocabulary list. Looking words up is SearchWordsQuery.
 */
class ListWordsQuery
{
    /**
     * This query performs no authorization. The caller is responsible for
     * checking the viewer may see David's words at all.
     *
     * @param  LearningStatus|null  $status  Keep only words with this status.
     * @param  bool  $includeFullDefinition  Whether to load `full_definition`.
     * @param  bool  $includeTimesMissed  Whether to count each word's misses,
     *                                    as `times_missed`. Always counted
     *                                    when sorting by most missed.
     */
    public function __construct(
        public WordSort $sort = WordSort::Recent,
        public ?LearningStatus $status = null,
        public bool $includeFullDefinition = false,
        public bool $includeTimesMissed = false,
    ) {}

    /**
     * Each listed word comes with its flashcard loaded, for its learning
     * status.
     *
     * @return LengthAwarePaginator<int, Word>
     */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        $words = Word::query()->select($this->columns())->with('flashcard');

        if ($this->status !== null) {
            $words->whereRelation('flashcard', 'learning_status', $this->status);
        }

        if ($this->includeTimesMissed || $this->sort === WordSort::MostMissed) {
            $words->withCount(['reviews as times_missed' => fn (Builder $reviews) => $reviews->where('rating', Rating::Again)]);
        }

        $this->applySort($words);

        return $words->paginate(perPage: $perPage, page: $page);
    }

    /**
     * How many words have each learning status, whatever status this query
     * keeps, so each one can be offered as a filter.
     * Counts flashcards, since every word has exactly one.
     *
     * @return array<value-of<LearningStatus>, int> Every status, in the enum's order.
     */
    public function countsByStatus(): array
    {
        $counts = Flashcard::query()
            ->whereMorphedTo('quizzable', Word::class)
            ->toBase()
            ->selectRaw('learning_status, count(*) as total')
            ->groupBy('learning_status')
            ->pluck('total', 'learning_status');

        return collect(LearningStatus::cases())
            ->mapWithKeys(fn (LearningStatus $status): array => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /**
     * What a listing needs. The full definition is left out unless asked for,
     * because it is by far the largest column.
     *
     * @return string[]
     */
    private function columns(): array
    {
        $columns = ['words.id', 'words.word', 'words.short_definition', 'words.created_at'];

        if ($this->includeFullDefinition) {
            $columns[] = 'words.full_definition';
        }

        return $columns;
    }

    /**
     * @param  Builder<Word>  $query
     */
    private function applySort(Builder $query): void
    {
        match ($this->sort) {
            WordSort::Recent => $query->orderBy('words.created_at', 'desc')->orderBy('words.id', 'desc'),
            WordSort::AToZ => $query->orderByRaw('lower(words.word) asc')->orderBy('words.id', 'asc'),
            WordSort::MostMissed => $query->orderBy('times_missed', 'desc')
                ->orderByRaw('lower(words.word) asc')
                ->orderBy('words.id', 'asc'),
        };
    }
}
