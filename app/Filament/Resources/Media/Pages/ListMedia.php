<?php

namespace App\Filament\Resources\Media\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Media\MediaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
