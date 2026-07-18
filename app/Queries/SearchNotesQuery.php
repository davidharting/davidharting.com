<?php

namespace App\Queries;

use App\Models\Note;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SearchNotesQuery
{
    public function __construct(public string $query) {}

    /**
     * @return Collection<int, Note>
     */
    public function execute(): Collection
    {
        return $this->builder()->get();
    }

    /**
     * @return LengthAwarePaginator<int, Note>
     */
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        return $this->builder()->paginate(
            perPage: $perPage,
            page: $page,
        );
    }

    /**
     * Visible notes whose title, lead, or markdown content contains the query,
     * matched case-insensitively, most recently published first.
     *
     * @return Builder<Note>
     */
    private function builder(): Builder
    {
        $pattern = '%'.LikePattern::escape($this->query).'%';

        return Note::query()
            ->where('visible', true)
            ->where(function (Builder $builder) use ($pattern): void {
                $builder->whereLike('title', $pattern)
                    ->orWhereLike('lead', $pattern)
                    ->orWhereLike('markdown_content', $pattern);
            })
            ->orderByDesc('published_at');
    }
}
