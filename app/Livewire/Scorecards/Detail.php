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
    public bool $drawer;

    #[Locked]
    public Scorecard $scorecard;

    /**
     * Used for the "record new round" form
     */
    #[Validate([
        'newRoundScores' => 'array|required',
        'newRoundScores.*' => 'integer|required|min:-100000|max:100000',
    ])]
    public $newRoundScores;

    public ?int $selectedRound;

    #[Validate('email')]
    public ?string $email;

    public function mount(Scorecard $scorecard)
    {
        $this->drawer = false;
        $this->scorecard = $scorecard;
        $this->newRoundScores = [];
        $this->selectedRound = null;
        $this->email = null;
    }

    public function render()
    {
        return view('livewire.scorecards.detail');
    }

    public function openNewRoundForm()
    {
        $this->selectedRound = null;
        $this->drawer = true;
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
        $scorecard = $this->scorecard;
        $collection = Score::whereIn('player_id', function (Builder $query) use ($scorecard) {
            $query->select('id')->from('players')->where('scorecard_id', $scorecard->id);
        })
            ->groupBy('round')
            ->orderBy('round', 'asc')
            ->select('round as round_number')->addSelect(DB::raw('json_agg(score order by player_id asc) as round_scores'))
            ->get();

        $data = $collection->map(function ($item) {
            // @phpstan-ignore-next-line
            return array_merge([$item->round_number], json_decode($item->round_scores));
        });

        return $data;
    }

    #[Computed]
    public function totals(): array
    {
        $scorecard = $this->scorecard;
        $totals = Score::whereIn('player_id', function (Builder $query) use ($scorecard) {
            $query->select('id')->from('players')->where('scorecard_id', $scorecard->id);
        })->orderBy('player_id')->groupBy('player_id')->select('player_id', DB::raw('sum(score) as total'))->get()->toArray();

        return array_merge(['Total'], array_column($totals, 'total'));
    }

    public function submit()
    {
        $this->validateOnly('newRoundScores');

        $player_ids = $this->scorecard->players()->orderBy('id', 'asc')->select('id')->pluck('id')->toArray();

        $round = $this->selectedRound ?? Score::whereIn('player_id', $player_ids)->max('round') + 1;

        $toInsert = collect($player_ids)->map(function ($player_id, $index) use ($round) {
            return [
                'player_id' => $player_id,
                'round' => $round,
                'score' => $this->newRoundScores[$index],
            ];
        });

        Score::upsert($toInsert->toArray(), ['player_id', 'round'], ['score']);

        $this->closeDrawer();
    }

    public function openEditForm(int $round)
    {
        $this->drawer = true;
        $this->selectedRound = $round;
        $scorecard = $this->scorecard;
        // TODO: Rename newRoundScores to roundScores or something like that
        $this->newRoundScores = Score::where('round', $round)->whereIn('player_id', function (Builder $query) use ($scorecard) {
            $query->select('id')->from('players')->where('scorecard_id', $scorecard->id);
        })->orderBy('player_id', 'asc')->pluck('score')->toArray();
    }

    public function closeDrawer()
    {
        $this->drawer = false;
        $this->newRoundScores = [];
        $this->selectedRound = null;
    }

    public function emailLink()
    {
        $this->validateOnly('email');
    }
}
