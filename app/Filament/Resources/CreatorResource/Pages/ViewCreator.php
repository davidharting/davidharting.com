<?php

namespace App\Filament\Resources\CreatorResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\CreatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCreator extends ViewRecord
{
    protected static string $resource = CreatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
