<?php

namespace App\Livewire\Scorecards;

use App\Models\Score;
use App\Models\Scorecard;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Detail extends Component
{
    #[Locked]
    public Scorecard $scorecard;

    /**
     * Used for the "record new round" form
     */
    #[Validate([
        'newRoundScores' => 'array|required',
        'newRoundScores.*' => 'integer|required|min:-100000|max:100000'
    ])]
    public $newRoundScores;

    public function mount(Scorecard $scorecard)
    {
        $this->scorecard = $scorecard;
        $this->newRoundScores = [];
    }

    public function render()
    {
        return view('livewire.scorecards.detail');
    }


    /**
     * Get the names of the players in order of their id ascending. 
     * Returns an array of strings
     */
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

    #[Computed]
    public function totals(): array
    {
        $totals = Score::whereIn('player_id', function (Builder $query) {
            $query->select('player_id')->from('scorecards')->where('id', $this->scorecard->id);
        })->orderBy('player_id')->groupBy('player_id')->select('player_id', DB::raw('sum(score) as total'))->get()->toArray();

        return array_merge(['Total'], array_column($totals, 'total'));
    }

    public function recordNewRound()
    {
        $this->validate();

        $player_ids = $this->scorecard->players()->orderBy('id', 'asc')->pluck('id')->toArray();
        $round = Score::whereIn('player_id', $player_ids)->max('round') + 1;


        $toInsert = collect($player_ids)->map(function ($player_id, $index) use ($round) {
            return [
                'player_id' => $player_id,
                'round' => $round,
                'score' => $this->newRoundScores[$index]
            ];
        });

        DB::table('scores')->insert($toInsert->toArray());
    }
}
