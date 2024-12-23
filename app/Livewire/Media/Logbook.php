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
    #[Url(except: '')]
    public string $year = '';

    /**
     * @return int[]
     */
    #[Computed]
    public function years(): array
    {
        return (new LogbookQuery)->years();
    }

    /**
     * @returns {
     *   'year': ?int,
     *   'type': ?MediaTypeName
     * }
     */
    private function getQueryFilters(): array
    {

        return [
            'year' => empty($this->year) ? null : (int) $this->year,
        ];
    }

    #[Computed]
    public function items(): Collection
    {
        $filters = $this->getQueryFilters();

        return (new LogbookQuery(
            $filters['year'],
        ))->execute();
    }

    public function mount() {}

    public function render()
    {
        return view('livewire.media.logbook');
    }
}
