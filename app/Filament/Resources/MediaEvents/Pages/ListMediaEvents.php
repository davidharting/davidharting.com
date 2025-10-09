<?php

namespace App\Filament\Resources\MediaEvents\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\MediaEvents\MediaEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMediaEvents extends ListRecords
{
    protected static string $resource = MediaEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
