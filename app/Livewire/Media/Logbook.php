<?php

namespace App\Livewire\Media;

use App\Queries\Media\LogbookQuery;
use Illuminate\Support\Collection;
use Livewire\Component;

class Logbook extends Component
{
    public Collection $items;

    public function mount()
    {
        $this->items = (new LogbookQuery)->execute();
    }

    public function render()
    {
        return view('livewire.media.logbook');
    }
}
