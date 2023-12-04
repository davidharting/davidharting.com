<?php

namespace App\Livewire;

use App\Models\Scorecard;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CreateScorecard extends Component
{
    // public int $title 

    #[Validate('required|integer|min:1|max:10')]
    public int $playerCount;

    #[Validate([
        'names' => 'array|required|min:1|max:10',
        'names.*' => 'string|required|min:1|max:50'
    ])]
    public $names;

    public function mount()
    {
        $this->playerCount = 2;
        $this->names = [];
    }

    public function create()
    {
        $this->validate();

        $scorecard = Scorecard::create([
            'title' => 'todo: title',
        ]);

        $scorecard->players()->createMany(collect($this->names)->map(function ($name) {
            return ['name' => $name];
        })->toArray());

        $this->redirect(route('scorecards.show', $scorecard));
    }

    public function render()
    {
        return view('livewire.create-scorecard');
    }
}
