<?php

namespace App\Support;

use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

/**
 * Passport's OAuth endpoints (/oauth/token, /oauth/authorize) pass an
 * attacker-controlled `client_id` straight into a lookup against
 * `oauth_clients.id`, a Postgres `uuid` column. A malformed value (anything
 * that isn't a UUID) makes Postgres raise `invalid input syntax for type
 * uuid`, which escapes as an uncaught QueryException — a 500 rather than the
 * OAuth `invalid_client` error a well-formed but unmatched id already gets.
 *
 * Rejecting the malformed case here, before it reaches the query, keeps both
 * cases behaving the same way: `find()` returns null, and every caller
 * (Bridge\ClientRepository::getClientEntity(), validateClient(), etc.)
 * already treats a null client as "no such client".
 */
class PassportClientRepository extends ClientRepository
{
    public function find(string|int $id): ?Client
    {
        if (is_string($id) && ! Str::isUuid($id)) {
            return null;
        }

        return parent::find($id);
    }
}
