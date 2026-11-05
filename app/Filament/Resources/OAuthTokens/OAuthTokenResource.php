<?php

namespace App\Filament\Resources\OAuthTokens;

use App\Enum\OAuthTokenStatus;
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
use Throwable;
use UnitEnum;

/**
 * Access tokens issued to OAuth clients. Each one's refresh token is handled
 * alongside it and never shown on its own.
 *
 * Read-only apart from revoking and deleting.
 *
 * The user's email is selected with a subquery, not through Token::user().
 * That relation reads the token's own client to pick a user provider, so it
 * breaks on the blank model Filament builds relation queries from.
 *
 * Below md the Issued column is hidden, so the client column also says when
 * the token was issued, to tell rows apart on a phone.
 */
class OAuthTokenResource extends Resource
{
    protected static ?string $model = Token::class;

    protected static ?string $modelLabel = 'OAuth token';

    protected static ?string $navigationLabel = 'Tokens';

    protected static ?string $slug = 'oauth-tokens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'OAuth';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select($query->qualifyColumn('*'))
                ->addSelect(['user_email' => User::query()
                    ->select('email')
                    ->whereColumn('users.id', $query->qualifyColumn('user_id')),
                ]))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->description(fn (Token $record): string => 'Issued '.$record->created_at->diffForHumans())
                    ->searchable(),
                TextColumn::make('status')
                    ->state(fn (Token $record): OAuthTokenStatus => OAuthTokenStatus::of($record))
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Issued')
                    ->dateTime()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('user_email')
                    ->label('User')
                    ->visibleFrom('md')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereIn(
                        'user_id',
                        User::query()->select('id')->whereLike('email', '%'.LikePattern::escape($search).'%'),
                    )),
                TextColumn::make('scopes')
                    ->badge()
                    ->visibleFrom('lg'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OAuthTokenStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? OAuthTokenStatus::from($data['value'])->scope($query)
                        : $query),
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
                        ->action(function (Token $record): void {
                            OAuthRevocation::revokeToken($record);
                        })
                        ->successNotificationTitle('Token revoked'),
                    DeleteAction::make()
                        ->modalDescription('The token and its refresh token are deleted for good, and stop working at once.')
                        ->using(fn (Token $record): bool => OAuthRevocation::deleteToken($record)),
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
                        ->action(function (Collection $records): void {
                            foreach ($records as $token) {
                                OAuthRevocation::revokeToken($token);
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Tokens revoked'),
                    DeleteBulkAction::make()
                        ->modalDescription('The tokens and their refresh tokens are deleted for good, and stop working at once.')
                        ->using(function (DeleteBulkAction $action, Collection $records): void {
                            foreach ($records as $token) {
                                try {
                                    OAuthRevocation::deleteToken($token) || $action->reportBulkProcessingFailure();
                                } catch (Throwable $exception) {
                                    $action->reportBulkProcessingFailure();
                                    report($exception);
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOAuthTokens::route('/'),
        ];
    }
}
