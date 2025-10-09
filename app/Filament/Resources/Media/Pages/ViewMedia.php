<?php

namespace App\Filament\Resources\Media\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\Media\MediaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMedia extends ViewRecord
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
