<?php

namespace App\Filament\Resources\MediaEvents\Pages;

use App\Filament\Resources\MediaEvents\MediaEventResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
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
