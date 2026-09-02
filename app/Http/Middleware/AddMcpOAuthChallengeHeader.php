<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emit the OAuth challenge header on MCP routes, which laravel/mcp cannot do itself.
 *
 * ---------------------------------------------------------------------------
 * REMOVING THIS CLASS
 * ---------------------------------------------------------------------------
 * This is a workaround for an upstream bug. Delete it once a *released* version
 * of laravel/mcp contains https://github.com/laravel/mcp/pull/322. Removal is:
 *
 *   1. Delete this class and its entry in App\Http\Kernel::$middleware.
 *   2. Delete tests/Feature/Mcp/OAuthChallengeHeaderTest.php.
 *   3. Confirm an anonymous request to /mcp/admin still returns a 401 carrying
 *      WWW-Authenticate — PR #322 registers the package middleware globally, so
 *      it takes over this job exactly.
 *
 * Tracked as issue #194. Check whether a release has it with:
 *
 *   gh api repos/laravel/mcp/compare/<latest-tag>...e4c4ea431544f0b29d56adffcbcfcacf15c27981
 *
 * A `behind_by` of 0 means the tag contains the fix. As of 2026-09-01 no tag did:
 * the fix merged to main on 2026-08-17, after both v0.9.4 and v1.0.0-beta.1.
 *
 * Note we cannot simply upgrade to laravel/mcp 1.x to get the fix. 1.x serves only
 * MCP protocol 2026-07-28 and drops the `initialize` handshake, which Claude.ai
 * still sends — it fails at the transport layer before auth is ever reached. See
 * issue #193 and PR #195 for the evidence.
 *
 * ---------------------------------------------------------------------------
 * WHY IT IS NEEDED
 * ---------------------------------------------------------------------------
 * The 401 challenge is what triggers MCP OAuth discovery in a client, so an
 * OAuth-protected MCP route that returns a bare 401 is undiscoverable.
 *
 * laravel/mcp attaches AddWwwAuthenticateHeader as *route* middleware, but Laravel
 * sorts Illuminate\Auth\Middleware\Authenticate to the front of the stack via
 * $middlewarePriority. So `auth:api` produces its 401 outside the decorator, which
 * never sees the response. See https://github.com/laravel/mcp/issues/278
 *
 * ---------------------------------------------------------------------------
 * WHY IT IS SHAPED THIS WAY
 * ---------------------------------------------------------------------------
 * Registered **globally** (Kernel::$middleware) because that is the only stack that
 * wraps `auth:api`. Route middleware — the thing that is broken — cannot.
 *
 * Scoped to **MCP paths** because AddWwwAuthenticateHeader only checks the status
 * code and never checks whether the route is an MCP route. Registering it globally
 * as-is (which is what PR #322 does upstream) would decorate *every* 401 in the app
 * with an MCP OAuth challenge. Upstream can accept that; a site with its own auth
 * should not.
 *
 * It **delegates** to the package middleware rather than reimplementing the header,
 * so the challenge we emit is byte-for-byte what laravel/mcp emits, and deleting
 * this class cannot change the response.
 */
class AddMcpOAuthChallengeHeader
{
    public function __construct(private readonly AddWwwAuthenticateHeader $challenge) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('mcp', 'mcp/*')) {
            return $next($request);
        }

        return $this->challenge->handle($request, $next);
    }
}
