<?php

namespace App\Filament\Resources\Words\Pages;

use App\Filament\Resources\Words\WordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWord extends EditRecord
{
    protected static string $resource = WordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
