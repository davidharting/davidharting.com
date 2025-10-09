<?php

namespace App\Filament\Resources\CreatorResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\CreatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCreators extends ListRecords
{
    protected static string $resource = CreatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
