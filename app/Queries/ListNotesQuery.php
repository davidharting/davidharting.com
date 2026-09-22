<?php

namespace App\Queries;

use App\Models\Note;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListNotesQuery
{
    /**
     * Everything a caller needs to list notes, minus the body. `visible` is
     * here because a caller passing $includeDrafts has to be able to tell the
     * two apart in the result.
     */
    private const COLUMNS = [
        'id',
        'slug',
        'title',
        'lead',
        'visible',
        'published_at',
    ];

    /**
     * This query performs no authorization. Both flags are the caller's to set,
     * and the caller is responsible for having earned them.
     *
     * @param  bool  $includeDrafts  Whether notes with `visible = false` are in the result.
     * @param  bool  $includeContent  Whether to load `markdown_content`. Off by default:
     *                                a listing renders titles, so loading every body is
     *                                the expensive half of the row for nothing.
     */
    public function __construct(
        public bool $includeDrafts = false,
        public bool $includeContent = false,
    ) {}

    /**
     * @return Collection<int, Note>
     */
    public function execute(): Collection
    {
        return $this->builder()->get($this->columns());
    }

    /**
     * @return LengthAwarePaginator<int, Note>
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
     * @return string[]
     */
    private function columns(): array
    {
        return $this->includeContent
            ? [...self::COLUMNS, 'markdown_content']
            : self::COLUMNS;
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
