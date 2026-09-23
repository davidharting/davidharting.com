<?php

namespace App\Queries;

use App\Models\Note;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SearchNotesQuery
{
    /**
     * This query performs no authorization. The caller sets $includeDrafts and
     * is responsible for authz checks.
     *
     * @param  bool  $includeDrafts  Whether notes with `visible = false` can match.
     */
    public function __construct(
        public string $query,
        public bool $includeDrafts = false,
    ) {}

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
     * Notes whose title, lead, or markdown content contains the query, matched
     * case-insensitively, most recently published first.
     *
     * @return Builder<Note>
     */
    private function builder(): Builder
    {
        $pattern = '%'.LikePattern::escape($this->query).'%';

        return Note::query()
            ->unless($this->includeDrafts, fn (Builder $builder) => $builder->where('visible', true))
            ->where(function (Builder $builder) use ($pattern): void {
                $builder->whereLike('title', $pattern)
                    ->orWhereLike('lead', $pattern)
                    ->orWhereLike('markdown_content', $pattern);
            })
            ->orderByDesc('published_at');
    }
}
