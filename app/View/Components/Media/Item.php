<?php

namespace App\View\Components\Media;

use App\Enum\MediaTypeName;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use stdClass;

class Item extends Component
{
    public function __construct(public readonly stdClass $item) {}

    public function icon(): string
    {
        return match (MediaTypeName::from($this->item->type)) {
            MediaTypeName::Book => '📕',
            MediaTypeName::Movie => '🍿',
            MediaTypeName::Album => '📀',
            MediaTypeName::TvShow => '📺',
            MediaTypeName::VideoGame => '🎮',
            default => '',
        };
    }

    public function getDate(): string
    {
        return Carbon::parse($this->item->occurred_at)->format('Y F d');
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.media.item');
    }
}
