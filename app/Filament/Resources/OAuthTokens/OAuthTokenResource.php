<?php

namespace App\Filament\Resources\OAuthTokens;

use App\Filament\Resources\OAuthTokens\Pages\ListOAuthTokens;
use App\Models\User;
use App\Support\LikePattern;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Passport\Token;
use UnitEnum;

/**
 * Access tokens issued to OAuth clients. Each one's refresh token is handled
 * alongside it and never shown on its own.
 *
 * Read-only apart from revoking and deleting.
 */
class OAuthTokenResource extends Resource
{
    protected static ?string $model = Token::class;

    protected static ?string $modelLabel = 'OAuth token';

    protected static ?string $slug = 'oauth-tokens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'OAuth';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // Passport's Token::user() relation reads the token's own client to
            // pick a user provider, so it breaks on the blank model Filament
            // builds relation queries from. Select the email directly instead.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select('oauth_access_tokens.*')
                ->addSelect(['user_email' => User::query()
                    ->select('email')
                    ->whereColumn('users.id', 'oauth_access_tokens.user_id'),
                ]))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable(),
                TextColumn::make('user_email')
                    ->label('User')
                    ->visibleFrom('md')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereIn(
                        'user_id',
                        User::query()->select('id')->where('email', 'ilike', '%'.LikePattern::escape($search).'%'),
                    )),
                TextColumn::make('scopes')
                    ->badge()
                    ->visibleFrom('lg'),
                TextColumn::make('status')
                    ->state(fn (Token $record): string => self::status($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Expired' => 'gray',
                        'Revoked' => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->label('Issued')
                    ->dateTime()
                    ->sortable()
                    ->visibleFrom('lg'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'revoked' => 'Revoked',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'active' => $query->where('revoked', false)
                            ->where(fn (Builder $unexpired) => $unexpired->whereNull('expires_at')->orWhere('expires_at', '>', now())),
                        'expired' => $query->where('revoked', false)->where('expires_at', '<=', now()),
                        'revoked' => $query->where('revoked', true),
                        default => $query,
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('revoke')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Revoke token')
                        ->modalDescription('The token and its refresh token stop working at once. The record stays, marked revoked.')
                        ->hidden(fn (Token $record): bool => $record->revoked)
                        ->action(fn (Token $record) => OAuthRevocation::revokeToken($record))
                        ->successNotificationTitle('Token revoked'),
                    DeleteAction::make()
                        ->modalDescription('The token and its refresh token are deleted for good, and stop working at once.')
                        ->using(fn (Token $record) => OAuthRevocation::deleteToken($record)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('revoke')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Revoke selected tokens')
                        ->modalDescription('Each token and its refresh token stop working at once. The records stay, marked revoked.')
                        ->action(fn (Collection $records) => $records->each(OAuthRevocation::revokeToken(...)))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Tokens revoked'),
                    DeleteBulkAction::make()
                        ->modalDescription('The tokens and their refresh tokens are deleted for good, and stop working at once.')
                        ->using(fn (Collection $records) => $records->each(OAuthRevocation::deleteToken(...))),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOAuthTokens::route('/'),
        ];
    }

    private static function status(Token $token): string
    {
        return match (true) {
            $token->revoked => 'Revoked',
            $token->expires_at?->isPast() ?? false => 'Expired',
            default => 'Active',
        };
    }
}
