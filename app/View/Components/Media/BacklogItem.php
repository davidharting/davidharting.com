<?php

namespace App\View\Components\Media;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\View\Component;
use stdClass;

class BacklogItem extends Component
{
    public function __construct(public readonly stdClass $item) {}

    public function icon(): string
    {
        return match ($this->item->type) {
            'book' => '📕',
            'movie' => '🍿',
            'album' => '📀',
            'tv show' => '📺',
            'video game' => '🎮',
            default => '',
        };
    }

    public function getAddedAt(): string
    {
        return Carbon::parse($this->item->added_at)->format('Y F d');
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.media.backlog-item');
    }
}
