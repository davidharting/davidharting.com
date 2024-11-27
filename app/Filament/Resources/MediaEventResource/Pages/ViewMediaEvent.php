<?php

namespace App\Filament\Resources\MediaEventResource\Pages;

use App\Filament\Resources\MediaEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMediaEvent extends ViewRecord
{
    protected static string $resource = MediaEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
