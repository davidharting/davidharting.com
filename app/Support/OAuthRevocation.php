<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

/**
 * Revokes or deletes OAuth clients and access tokens together with the rows
 * that depend on them.
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
 * Revoking the client has no such gap: a revoked client is refused outright.
 */
class OAuthRevocation
{
    /**
     * Revoke a client, every token it issued, their refresh tokens and any
     * pending authorization codes.
     *
     * A revoked client is refused at the resource server, so this alone cuts a
     * connector off. Its tokens are revoked as well so that the record reads
     * correctly and un-revoking the client would not quietly restore them.
     */
    public static function revokeClient(Client $client): void
    {
        DB::transaction(function () use ($client): void {
            $tokenIds = self::tokenIdsFor($client);

            Passport::refreshToken()->newQuery()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
            Passport::token()->newQuery()->where('client_id', $client->getKey())->update(['revoked' => true]);
            Passport::authCode()->newQuery()->where('client_id', $client->getKey())->update(['revoked' => true]);
            Passport::deviceCode()->newQuery()->where('client_id', $client->getKey())->update(['revoked' => true]);

            $client->forceFill(['revoked' => true])->save();
        });
    }

    /**
     * Delete a client and every row that belongs to it.
     */
    public static function deleteClient(Client $client): bool
    {
        return DB::transaction(function () use ($client): bool {
            $tokenIds = self::tokenIdsFor($client);

            Passport::refreshToken()->newQuery()->whereIn('access_token_id', $tokenIds)->delete();
            Passport::token()->newQuery()->where('client_id', $client->getKey())->delete();
            Passport::authCode()->newQuery()->where('client_id', $client->getKey())->delete();
            Passport::deviceCode()->newQuery()->where('client_id', $client->getKey())->delete();

            return (bool) $client->delete();
        });
    }

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

    /**
     * The ids of the client's access tokens, as a subquery.
     *
     * @return Builder<Token>
     */
    private static function tokenIdsFor(Client $client): Builder
    {
        return Passport::token()->newQuery()->where('client_id', $client->getKey())->select('id');
    }
}
