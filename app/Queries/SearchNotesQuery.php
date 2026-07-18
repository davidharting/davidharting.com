<?php

namespace App\Queries;

use App\Models\Note;
use App\Support\LikePattern;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;

class SearchNotesQuery
{
    public function __construct(public string $query) {}

    /**
     * Find visible notes whose title, lead, or markdown content contains the
     * query, matched case-insensitively, most recently published first.
     *
     * @return Collection<int, Note>
     */
    public function execute(): Collection
    {
        $pattern = '%'.LikePattern::escape($this->query).'%';

        return Note::query()
            ->where('visible', true)
            ->where(function (Builder $builder) use ($pattern): void {
                $builder->whereLike('title', $pattern)
                    ->orWhereLike('lead', $pattern)
                    ->orWhereLike('markdown_content', $pattern);
            })
            ->orderByDesc('published_at')
            ->get();
    }
}
