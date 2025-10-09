<?php

namespace App\Filament\Resources\MediaEventResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\MediaEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMediaEvent extends ViewRecord
{
    protected static string $resource = MediaEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
