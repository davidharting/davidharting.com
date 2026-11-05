<?php

namespace App\Filament\Resources\OAuthTokens\Pages;

use App\Filament\Resources\OAuthTokens\OAuthTokenResource;
use Filament\Resources\Pages\ListRecords;

class ListOAuthTokens extends ListRecords
{
    protected static string $resource = OAuthTokenResource::class;
}
