<?php

namespace App\View\Components\Media;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use stdClass;

class Item extends Component
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

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.media.item');
    }
}
