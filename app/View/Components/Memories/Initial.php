<?php

namespace App\View\Components\Memories;

use App\Models\Memory;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * A person's initial in a circle: filled once their memory is shared, dashed
 * while it is a draft, faint before it is started.
 */
class Initial extends Component
{
    public function __construct(
        public ?Memory $memory,
        public string $name,
    ) {}

    public function initial(): string
    {
        return mb_substr($this->name, 0, 1);
    }

    /**
     * For example "Katie: draft".
     */
    public function label(): string
    {
        if ($this->memory === null) {
            return "{$this->name}: not started";
        }

        return $this->memory->isDraft() ? "{$this->name}: draft" : "{$this->name}: shared";
    }

    public function stateClasses(): string
    {
        if ($this->memory === null) {
            return 'border-base-300 border opacity-50';
        }

        if ($this->memory->isDraft()) {
            return 'border-warning border-2 border-dashed';
        }

        return 'bg-primary text-primary-content';
    }

    public function render(): View
    {
        return view('components.memories.initial');
    }
}
