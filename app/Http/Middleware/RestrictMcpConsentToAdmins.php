<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse MCP consent to non-admins, so they never receive an `mcp:use` token.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS NOT `can:administrate` ON A ROUTE
 * ---------------------------------------------------------------------------
 * The route belongs to Passport, not to us. `Mcp::oauthRoutes()` mints an
 * `mcp:use` token for any authenticated user who approves the consent screen,
 * and `/oauth/authorize` is one route serving every OAuth client — gating it
 * wholesale would be a global OAuth lockdown, not an MCP one. So the check has
 * to be narrower than the route: it keys off the *requested scope*.
 *
 * `PassportServiceProvider::registerRoutes()` applies `config('passport.middleware')`
 * to its whole route group and exposes no static API for it, so `config/passport.php`
 * is the only seam available. See issue #186 for the alternatives that were ruled
 * out (`Passport::ignoreRoutes()`, overriding the controller).
 *
 * ---------------------------------------------------------------------------
 * WHY IT IS BELT-AND-SUSPENDERS
 * ---------------------------------------------------------------------------
 * Issue #192 closed public registration, so there is no longer a supply of
 * self-registered non-admins to reject. This stops a non-admin *account* that
 * already exists from completing the dance and holding a token that only fails
 * later, at the resource.
 *
 * ---------------------------------------------------------------------------
 * IT FAILS OPEN, AND THAT IS LOAD-BEARING ELSEWHERE
 * ---------------------------------------------------------------------------
 * Every branch below passes the request through rather than denying it, so a
 * request that omits `scope`, spells it differently, or sends it somewhere
 * other than the query string is *not* stopped here. That is deliberate — a
 * consent screen that 403s the ordinary OAuth dance is worse than one that
 * occasionally waves a request through — but it means this class is not the
 * control that keeps a scope-less token out of `/mcp/admin`.
 *
 * `CheckToken::using('mcp:use')` on the server route is what does that, which
 * is why issue #187 records it as load-bearing rather than optional. Do not
 * remove it on the grounds that this middleware exists.
 *
 * The device grant (`/oauth/device/authorize`) is a second minting path this
 * deliberately does not cover; that is issue #199.
 */
class RestrictMcpConsentToAdmins
{
    /**
     * The scope Mcp::oauthRoutes() hardcodes for MCP access.
     */
    private const MCP_SCOPE = 'mcp:use';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isMcpConsentRequest($request)) {
            return $next($request);
        }

        $user = $request->user();

        /*
         * A guest arriving here is the normal first step of the dance, not an
         * intrusion: Passport's AuthorizationController::promptForLogin() sends
         * them to log in and they return authenticated. Checking the gate first
         * would 403 that, because Gate::denies() is true for a guest.
         */
        if ($user === null) {
            return $next($request);
        }

        if (Gate::forUser($user)->denies('administrate')) {
            abort(403, 'This account is not permitted to connect to the MCP server.');
        }

        return $next($request);
    }

    /**
     * Is this a consent request that asks for the MCP scope?
     *
     * Matched by route name rather than path, because `config('passport.path')`
     * can move the prefix. The scope is read from the query string, where the
     * authorization-code flow puts it; RFC 6749 §3.3 makes it a space-delimited
     * list, and Laravel has already URL-decoded `mcp%3Ause` by this point.
     */
    private function isMcpConsentRequest(Request $request): bool
    {
        if ($request->route()?->named('passport.authorizations.authorize') !== true) {
            return false;
        }

        $scope = $request->query('scope');

        if (! is_string($scope)) {
            return false;
        }

        return in_array(self::MCP_SCOPE, preg_split('/\s+/', $scope, -1, PREG_SPLIT_NO_EMPTY) ?: [], strict: true);
    }
}
