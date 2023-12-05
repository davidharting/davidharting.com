<?php

namespace App\Livewire;

use App\Models\Scorecard;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CreateScorecard extends Component
{
    #[Validate('required|string|max:300')]
    public string $title;

    #[Validate('required|integer|min:1|max:10')]
    public int $playerCount;

    #[Validate([
        'names' => 'array|required|min:1|max:10',
        'names.*' => 'string|required|min:1|max:50',
    ])]
    public $names;

    public function mount()
    {
        $this->playerCount = 2;
        $this->names = [];
        $this->title = '';
    }

    public function create()
    {
        $this->validate();

        $scorecard = Scorecard::create([
            'title' => $this->getTitle(),
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

    private function getTitle(): string
    {
        $title = Str::of($this->title)->trim();
        if ($title->isEmpty()) {
            return 'Scorecard';
        }

        return $title;
    }
}
