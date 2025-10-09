<?php

namespace App\Filament\Resources\MediaEvents\Pages;

use App\Filament\Resources\MediaEvents\MediaEventResource;
use Filament\Actions\EditAction;
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
