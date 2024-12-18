<?php

namespace App\Livewire\Media;

use App\Queries\Media\BacklogQuery;
use Illuminate\Support\Collection;
use Livewire\Component;

class Backlog extends Component
{
    public Collection $items;

    public function mount()
    {
        $this->items = (new BacklogQuery)->execute();
    }

    public function render()
    {
        return view('livewire.media.backlog');
    }
}
