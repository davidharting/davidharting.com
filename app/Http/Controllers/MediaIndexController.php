<?php

namespace App\Http\Controllers;

use App\Enum\MediaTypeName;
use App\Queries\Media\BacklogQuery;
use App\Queries\Media\InProgressQuery;
use App\Queries\Media\LogbookQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MediaIndexController extends Controller
{
    public function __invoke(Request $request): View
    {
        $list = $request->input('list', 'finished');
        $year = $request->input('year', '');
        $type = $request->input('type', '');

        $disableFilters = $list === 'in-progress';

        return view('media.index', [
            'items' => $this->query($list, $year, $type),
            'years' => $this->years(),
            'mediaTypes' => $this->mediaTypes(),
            'list' => $list,
            'year' => $year,
            'type' => $type,
            'disableFilters' => $disableFilters,
        ]);
    }

    private function mediaTypes(): array
    {
        return MediaTypeName::cases();
    }

    private function years(): array
    {
        return (new LogbookQuery)->years();
    }

    private function getYear(string $year): ?int
    {
        return $year ? (int) $year : null;
    }

    private function getType(string $type): ?MediaTypeName
    {
        return $type ? MediaTypeName::from($type) : null;
    }

    private function query(string $list, string $year, string $type): Collection
    {
        return match ($list) {
            'backlog' => (new BacklogQuery($this->getYear($year), $this->getType($type)))->execute(),
            'in-progress' => (new InProgressQuery)->execute(),
            default => (new LogbookQuery($this->getYear($year), $this->getType($type)))->execute(),
        };
    }
}
