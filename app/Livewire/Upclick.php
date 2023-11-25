<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

use App\Models\Upclick as UpclickModel;


use Illuminate\Support\Facades\Auth;


class Upclick extends Component
{
    #[Computed]
    #[Locked]
    public function total_count(): int
    {
        return UpclickModel::max('id') ?? 0;
    }

    #[Computed]
    #[Locked]
    public function user_count(): int | null
    {
        if (Auth::guest()) {
            return null;
        }

        return UpclickModel::where('user_id', Auth::id())->count() ?? 0;
    }

    public function click()
    {
        UpclickModel::create([
            'user_id' => Auth::id(),
        ]);
    }

    public function render()
    {
        return view('livewire.upclick');
    }
}
