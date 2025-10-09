<?php

namespace App\Filament\Resources\CreatorResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\CreatorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCreator extends EditRecord
{
    protected static string $resource = CreatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
