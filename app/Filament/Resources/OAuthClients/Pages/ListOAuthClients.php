<?php

namespace App\Filament\Resources\OAuthClients\Pages;

use App\Filament\Resources\OAuthClients\OAuthClientResource;
use Filament\Resources\Pages\ListRecords;

class ListOAuthClients extends ListRecords
{
    protected static string $resource = OAuthClientResource::class;
}
