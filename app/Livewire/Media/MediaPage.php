<?php

namespace App\Livewire\Media;

use App\Enum\MediaTypeName;
use App\Queries\Media\BacklogQuery;
use App\Queries\Media\InProgressQuery;
use App\Queries\Media\LogbookQuery;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class MediaPage extends Component
{
    #[Url(except: 'activity')]
    public string $list = 'activity';

    #[Url(except: '')]
    public string $year = '';

    #[Url(except: '')]
    public string $type = '';

    private function mediaTypes(): array
    {
        return MediaTypeName::cases();
    }

    private function years(): array
    {
        return (new LogbookQuery)->years();
    }

    private function getYear(): ?int
    {
        return $this->year ? (int) $this->year : null;
    }

    private function getType(): ?MediaTypeName
    {
        return $this->type ? MediaTypeName::from($this->type) : null;
    }

    private function query(): Collection
    {
        return match ($this->list) {
            'backlog' => (new BacklogQuery($this->getYear(), $this->getType()))->execute(),
            'in-progress' => (new InProgressQuery())->execute(),
            default => (new LogbookQuery($this->getYear(), $this->getType()))->execute(),
        };
    }

    public function render()
    {
        return view('livewire.media.media-page', [
            'items' => $this->query(),
            'years' => $this->years(),
            'mediaTypes' => $this->mediaTypes(),
        ]);
    }
}
