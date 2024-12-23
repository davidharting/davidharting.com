<?php

namespace App\Livewire\Media;

use App\Enum\MediaTypeName;
use App\Queries\Media\LogbookQuery;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Logbook extends Component
{
    public Collection $items;

    #[Url]
    public ?int $year;

    #[Url]
    public ?MediaTypeName $type;

    /**
     * @return int[]
     */
    #[Computed]
    public function years(): array
    {
        return $this->items
            ->pluck('finished_at_year')
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();
    }

    public function mount()
    {
        $this->items = (new LogbookQuery)->execute();
    }

    public function render()
    {
        return view('livewire.media.logbook');
    }
}
