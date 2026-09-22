<?php

namespace App\Queries;

use App\Models\Note;
use Illuminate\Database\Eloquent\Builder;

class GetNoteQuery
{
    /**
     * This query performs no authorization. The caller sets $includeDrafts and
     * is responsible for having earned it.
     *
     * @param  bool  $includeDrafts  Whether a note with `visible = false` can be
     *                               returned, rather than treated as absent.
     */
    public function __construct(
        public string $slug,
        public bool $includeDrafts = false,
    ) {}

    public function execute(): ?Note
    {
        return Note::query()
            ->where('slug', $this->slug)
            ->unless($this->includeDrafts, fn (Builder $builder) => $builder->where('visible', true))
            ->first();
    }
}
