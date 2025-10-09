<?php

namespace App\Filament\Resources\MediaEventResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\MediaEventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMediaEvent extends EditRecord
{
    protected static string $resource = MediaEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
