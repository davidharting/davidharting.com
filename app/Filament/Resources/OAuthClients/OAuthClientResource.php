<?php

namespace App\Filament\Resources\OAuthClients;

use App\Filament\Resources\OAuthClients\Pages\ListOAuthClients;
use App\Support\OAuthRevocation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Laravel\Passport\Client;
use UnitEnum;

/**
 * The applications allowed to request tokens: Claude.ai's dynamically
 * registered connector, and the personal access client `mcp:token` uses.
 *
 * Read-only apart from revoking and deleting. Clients are created by OAuth
 * flows, never by hand.
 */
class OAuthClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $modelLabel = 'OAuth client';

    protected static ?string $slug = 'oauth-clients';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'OAuth';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount(['tokens as active_tokens_count' => fn ($tokens) => $tokens
                ->where('revoked', false)
                ->where(fn ($unexpired) => $unexpired->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('status')
                    ->state(fn (Client $record): string => $record->revoked ? 'Revoked' : 'Active')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Revoked' ? 'danger' : 'success'),
                TextColumn::make('active_tokens_count')
                    ->label('Active tokens')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('id')
                    ->label('Client ID')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('redirect_uris')
                    ->label('Redirect URIs')
                    ->listWithLineBreaks()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('grant_types')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                TernaryFilter::make('revoked'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('revoke')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Revoke client')
                        ->modalDescription('The client and every token it holds stop working at once. The records stay, marked revoked.')
                        ->hidden(fn (Client $record): bool => $record->revoked)
                        ->action(fn (Client $record) => OAuthRevocation::revokeClient($record))
                        ->successNotificationTitle('Client revoked'),
                    DeleteAction::make()
                        ->modalDescription('The client and all of its tokens are deleted for good. Anything using them stops working at once.')
                        ->using(fn (Client $record) => OAuthRevocation::deleteClient($record)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('revoke')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Revoke selected clients')
                        ->modalDescription('Each client and every token it holds stop working at once. The records stay, marked revoked.')
                        ->action(fn (Collection $records) => $records->each(OAuthRevocation::revokeClient(...)))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Clients revoked'),
                    DeleteBulkAction::make()
                        ->modalDescription('The clients and all of their tokens are deleted for good. Anything using them stops working at once.')
                        ->using(fn (Collection $records) => $records->each(OAuthRevocation::deleteClient(...))),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOAuthClients::route('/'),
        ];
    }
}
