<?php

namespace App\Queries\Words;

use App\Enum\WordSort;
use App\Models\Word;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * One page of David's word list for the /words page: optionally narrowed to
 * words whose spelling or either definition contains $search (ignoring case,
 * with wildcards matched literally), in the order $sort asks for.
 */
class BrowseWordsQuery
{
    public function __construct(
        public ?string $search = null,
        public WordSort $sort = WordSort::Recent,
        public int $perPage = 100,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Word>
     */
    public function execute(): LengthAwarePaginator
    {
        $query = Word::query();

        if (filled($this->search)) {
            $pattern = '%'.LikePattern::escape($this->search).'%';

            $query->where(fn (Builder $query) => $query
                ->whereLike('word', $pattern)
                ->orWhereLike('short_definition', $pattern)
                ->orWhereLike('full_definition', $pattern));
        }

        match ($this->sort) {
            WordSort::Recent => $query->orderBy('created_at', 'desc')->orderBy('id', 'desc'),
            WordSort::Alphabetical => $query->orderByRaw('lower(word) asc')->orderBy('id', 'asc'),
        };

        return $query->paginate($this->perPage);
    }
}
