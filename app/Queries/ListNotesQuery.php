<?php

namespace App\Queries;

use App\Models\Note;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListNotesQuery
{
    /**
     * @param  bool  $includeDrafts  Whether notes with `visible = false` are in
     *                               the result. Only ever true for a caller that
     *                               has already passed `NotePolicy::viewAny` —
     *                               this flag expresses the decision, it does not
     *                               make it.
     */
    public function __construct(public bool $includeDrafts = false) {}

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
     * Notes, most recently published first.
     *
     * @return Builder<Note>
     */
    private function builder(): Builder
    {
        return Note::query()
            ->unless($this->includeDrafts, fn (Builder $builder) => $builder->where('visible', true))
            ->orderByDesc('published_at');
    }
}
