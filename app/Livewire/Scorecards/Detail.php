<?php

namespace App\Livewire\Scorecards;

use App\Models\Score;
use App\Models\Scorecard;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        return $this->scorecard->players()->orderBy('id', 'asc')->pluck('name')->toArray();
    }

    /**
     * Returns a 2D array. Each item represents a round. Each
     */
    #[Computed]
    public function rounds(): Collection
    {
        $collection =  Score::whereIn('player_id', function (Builder $query) {
            $query->select('player_id')->from('scorecards')->where('id', $this->scorecard->id);
        })
            ->groupBy('round')
            ->orderBy('round', 'asc')
            ->orderBy('player_id', 'asc')
            ->select('round as round_number')->addSelect(DB::raw('json_group_array(score) as round_scores'))
            ->get();



        $data = $collection->map(function ($item) {
            return array_merge([$item->round_number], json_decode($item->round_scores));
        });

        return $data;
    }
}
