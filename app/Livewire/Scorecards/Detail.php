<?php

namespace App\Livewire\Scorecards;

use App\Models\Scorecard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Detail extends Component
{
    #[Locked]
    public Scorecard $scorecard;

    public function mount(Scorecard $scorecard)
    {
        $this->scorecard = $scorecard;
    }

    public function render()
    {
        return view('livewire.scorecards.detail');
    }


    #[Computed]
    public function playerNames(): array
    {
        return $this->scorecard->players->pluck('name')->toArray();
    }
}
