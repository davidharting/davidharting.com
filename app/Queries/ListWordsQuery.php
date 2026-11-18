<?php

namespace App\Queries;

use App\Enum\LearningStatus;
use App\Enum\WordSort;
use App\Fsrs\Rating;
use App\Models\Review;
use App\Models\Word;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class ListWordsQuery
{
    /**
     * This query performs no authorization. The caller is responsible for
     * checking the viewer may see David's words at all.
     *
     * @param  string|null  $search  Matched case-insensitively against the word
     *                               and both of its definitions.
     * @param  LearningStatus|null  $status  Keep only words with this status.
     * @param  bool  $includeFullDefinition  Whether to load `full_definition`.
     */
    public function __construct(
        public ?string $search = null,
        public WordSort $sort = WordSort::Recent,
        public ?LearningStatus $status = null,
        public bool $includeFullDefinition = false,
    ) {}

    /**
     * Each listed word comes with its flashcard loaded, for its learning
     * status, and `times_missed`: how often David has missed it in practice.
     *
     * @return LengthAwarePaginator<int, Word>
     */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        return $this->searched()
            ->when($this->status, fn (Builder $query, LearningStatus $status) => $query->whereHas(
                'flashcard',
                fn (Builder $flashcard) => $flashcard->whereRaw(LearningStatus::sql().' = ?', [$status->value]),
            ))
            ->select($this->columns())
            ->addSelect(['times_missed' => $this->timesMissed()])
            ->with('flashcard')
            ->tap(fn (Builder $query) => $this->applySort($query))
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * How many words the search finds with each learning status, whatever
     * status this query keeps, so each one can be offered as a filter.
     *
     * @return array<value-of<LearningStatus>, int> Every status, in the enum's order.
     */
    public function countsByStatus(): array
    {
        $counts = $this->searched()
            ->join('flashcards', function (JoinClause $join): void {
                $join->on('flashcards.quizzable_id', '=', 'words.id')
                    ->where('flashcards.quizzable_type', (new Word)->getMorphClass());
            })
            ->toBase()
            ->selectRaw(LearningStatus::sql().' as status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

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
     * Words matching the search, in no particular order.
     *
     * @return Builder<Word>
     */
    private function searched(): Builder
    {
        return Word::query()
            ->when(filled($this->search), function (Builder $query): void {
                $pattern = '%'.LikePattern::escape(trim($this->search)).'%';

                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereLike('words.word', $pattern)
                        ->orWhereLike('words.short_definition', $pattern)
                        ->orWhereLike('words.full_definition', $pattern);
                });
            });
    }

    /**
     * How many times each word's flashcard was graded "Missed it".
     *
     * @return Builder<Review>
     */
    private function timesMissed(): Builder
    {
        return Review::query()
            ->selectRaw('count(*)')
            ->join('flashcards', 'flashcards.id', '=', 'reviews.flashcard_id')
            ->whereColumn('flashcards.quizzable_id', 'words.id')
            ->where('flashcards.quizzable_type', (new Word)->getMorphClass())
            ->where('reviews.rating', Rating::Again);
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
