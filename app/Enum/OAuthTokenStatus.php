<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Passport\Token;

/**
 * Whether an OAuth access token still works, and the one place that rule is
 * written down, both for a loaded token and as a query constraint.
 *
 * Judged by the access token alone. Its refresh token can outlive it, so an
 * Expired token may still be refreshable. That doesn't arise today, because
 * both lifetimes are Passport's default of one year.
 */
enum OAuthTokenStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public static function of(Token $token): self
    {
        return match (true) {
            $token->revoked => self::Revoked,
            $token->expires_at?->isPast() ?? false => self::Expired,
            default => self::Active,
        };
    }

    /**
     * Narrow a query on oauth_access_tokens to tokens with this status.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scope(Builder $query): Builder
    {
        return match ($this) {
            self::Active => $query->where('revoked', false)
                ->where(fn (Builder $unexpired): Builder => $unexpired->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            self::Expired => $query->where('revoked', false)->where('expires_at', '<=', now()),
            self::Revoked => $query->where('revoked', true),
        };
    }

    public function getLabel(): string
    {
        return $this->name;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Expired => 'gray',
            self::Revoked => 'danger',
        };
    }
}
