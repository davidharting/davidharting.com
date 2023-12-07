<?php

namespace App\Livewire;

use App\Models\Upclick as UpclickModel;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Upclick extends Component
{
    #[Computed]
    #[Locked]
    public function total_count(): int
    {
        return UpclickModel::count() ?? 0;
    }

    #[Computed]
    #[Locked]
    public function user_count(): ?int
    {
        if (Auth::guest()) {
            return null;
        }

        return UpclickModel::where('user_id', Auth::id())->count();
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
