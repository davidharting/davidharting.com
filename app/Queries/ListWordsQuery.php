<?php

namespace App\Queries;

use App\Enum\WordSort;
use App\Models\Word;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListWordsQuery
{
    /**
     * This query performs no authorization. The caller is responsible for
     * checking the viewer may see David's words at all.
     *
     * @param  string|null  $search  Matched case-insensitively against the word
     *                               and both of its definitions.
     * @param  bool  $includeFullDefinition  Whether to load `full_definition`.
     */
    public function __construct(
        public ?string $search = null,
        public WordSort $sort = WordSort::Recent,
        public bool $includeFullDefinition = false,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Word>
     */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        return $this->builder()->paginate(
            perPage: $perPage,
            columns: $this->columns(),
            page: $page,
        );
    }

    /**
     * What a listing needs. The full definition is left out unless asked for,
     * because it is by far the largest column.
     *
     * @return string[]
     */
    private function columns(): array
    {
        $columns = ['id', 'word', 'short_definition', 'created_at'];

        if ($this->includeFullDefinition) {
            $columns[] = 'full_definition';
        }

        return $columns;
    }

    /**
     * @return Builder<Word>
     */
    private function builder(): Builder
    {
        return Word::query()
            ->when(filled($this->search), function (Builder $query): void {
                $pattern = '%'.LikePattern::escape(trim($this->search)).'%';

                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereLike('word', $pattern)
                        ->orWhereLike('short_definition', $pattern)
                        ->orWhereLike('full_definition', $pattern);
                });
            })
            ->tap(fn (Builder $query) => $this->applySort($query));
    }

    /**
     * @param  Builder<Word>  $query
     */
    private function applySort(Builder $query): void
    {
        match ($this->sort) {
            WordSort::Recent => $query->orderBy('created_at', 'desc')->orderBy('id', 'desc'),
            WordSort::AToZ => $query->orderByRaw('lower(word) asc')->orderBy('id', 'asc'),
        };
    }
}
