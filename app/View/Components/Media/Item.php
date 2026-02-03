<?php

namespace App\View\Components\Media;

use App\Enum\MediaTypeName;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use stdClass;

class Item extends Component
{
    public function __construct(
        public readonly stdClass $item,
        public readonly bool $canViewMedia = false,
        public readonly bool $canAdministrate = false,
        public readonly bool $canSeeNote = false,
    ) {}

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

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.media.item');
    }
}
