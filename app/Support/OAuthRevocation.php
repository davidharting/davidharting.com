<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

/**
 * Revokes or deletes OAuth access tokens together with the rows that depend
 * on them.
 *
 * Passport stores an access token and its refresh token as separate rows, and
 * nothing cascades between its tables. Revoking or deleting only an access
 * token would leave its refresh token able to mint a fresh one, so every
 * operation here always covers the dependents too.
 *
 * Passport treats a missing row the same as a revoked one, so revoking and
 * deleting both cut access off. Revoking keeps the record for later reference.
 *
 * These are bulk queries, so Passport's AccessTokenRevoked and
 * RefreshTokenRevoked events don't fire. Nothing in the app listens for them.
 *
 * Revoking a single token can lose a race with a refresh that reads its
 * refresh token just before the revoke commits, leaving a fresh token pair.
 */
class OAuthRevocation
{
    /**
     * Revoke an access token and its refresh token.
     */
    public static function revokeToken(Token $token): void
    {
        DB::transaction(function () use ($token): void {
            Passport::refreshToken()->newQuery()->where('access_token_id', $token->getKey())->update(['revoked' => true]);

            $token->revoke();
        });
    }

    /**
     * Delete an access token and its refresh token.
     */
    public static function deleteToken(Token $token): bool
    {
        return DB::transaction(function () use ($token): bool {
            Passport::refreshToken()->newQuery()->where('access_token_id', $token->getKey())->delete();

            return (bool) $token->delete();
        });
    }
}
