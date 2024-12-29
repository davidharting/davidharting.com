<?php

namespace App\Livewire\Media;

use App\Enum\MediaTypeName;
use App\MediaList;
use App\Queries\Media\LogbookQuery;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class MediaPage extends Component
{
    #[Url(except: 'logbook')]
    public string $list = 'logbook';

    #[Url(except: '')]
    public string $year = '';

    #[Url(except: '')]
    public string $type = '';

    public function rules()
    {
        return [
            'list' => Rule::enum(MediaList::class),
            'year' => 'integer',
            'type' => Rule::enum(MediaTypeName::class),
        ];
    }

    private function mediaTypes(): array
    {
        return MediaTypeName::cases();
    }

    private function years(): array
    {
        return (new LogbookQuery)->years();
    }

    public function render()
    {
        return view('livewire.media.media-page', [
            'items' => Collection::make([]),
            'years' => $this->years(),
            'mediaTypes' => $this->mediaTypes(),
        ]);
    }
}
