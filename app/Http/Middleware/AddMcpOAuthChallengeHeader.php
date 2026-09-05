<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Mcp\Facades\Mcp;
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
 *      it takes over this job.
 *
 * Note that #322 registers it *unscoped*, so removing this class also means
 * accepting an MCP OAuth challenge on every 401 in the app (see WHY IT IS
 * SCOPED below). Check that trade before deleting.
 *
 * Tracked as issue #194. To check whether a release contains the fix:
 *
 *   gh api repos/laravel/mcp/compare/<tag>...e4c4ea431544f0b29d56adffcbcfcacf15c27981 \
 *     --jq '{status, ahead_by}'
 *
 * Read it carefully: the fix commit is the *head* of that comparison, so
 * `ahead_by: 0` (status "behind" or "identical") is what means the tag already
 * contains it. A status of "ahead" means the fix landed *after* the tag, i.e.
 * the tag does NOT have it. As of 2026-09-05 v1.0.0-beta.1 reports
 * `ahead_by: 1` — still missing the fix.
 *
 * Upgrading is also gated on something else entirely: laravel/mcp 1.x serves
 * only MCP protocol 2026-07-28 and drops the `initialize` handshake, which
 * Claude.ai still sends. It fails at the transport layer before auth is
 * reached, so 1.x currently makes /mcp/admin unreachable from the client we
 * care about. See issues #193 and #194, and PR #195.
 *
 * ---------------------------------------------------------------------------
 * WHY IT IS NEEDED
 * ---------------------------------------------------------------------------
 * The 401 challenge is what triggers OAuth discovery in an MCP client, so an
 * OAuth-protected MCP route returning a bare 401 is undiscoverable.
 *
 * laravel/mcp attaches AddWwwAuthenticateHeader as *route* middleware, but
 * Laravel sorts Illuminate\Auth\Middleware\Authenticate to the front of the
 * stack via $middlewarePriority. So `auth:api` produces its 401 outside the
 * decorator, which never sees the response.
 * See https://github.com/laravel/mcp/issues/278
 *
 * ---------------------------------------------------------------------------
 * WHY IT IS GLOBAL
 * ---------------------------------------------------------------------------
 * Kernel::$middleware is the only stack that wraps `auth:api`. Route middleware
 * — the thing that is broken — cannot.
 *
 * ---------------------------------------------------------------------------
 * WHY IT IS SCOPED
 * ---------------------------------------------------------------------------
 * AddWwwAuthenticateHeader only inspects the status code; it never checks
 * whether the route is an MCP route. Registering it globally as-is (what #322
 * does upstream) would decorate *every* 401 in the app — including the web UI's
 * — with an MCP OAuth challenge, pointing browsers at MCP resource metadata.
 * Upstream can accept that; a site with its own auth should not.
 *
 * ---------------------------------------------------------------------------
 * WHY IT DELEGATES
 * ---------------------------------------------------------------------------
 * The header itself is built by laravel/mcp, not by us, so the challenge we
 * emit is byte-for-byte what the package emits and deleting this class cannot
 * change the response. We only decide *whether* it runs.
 */
class AddMcpOAuthChallengeHeader
{
    public function __construct(private readonly AddWwwAuthenticateHeader $challenge) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->isMcpServerRoute($request)) {
            return $response;
        }

        return $this->challenge->handle($request, fn (): Response => $response);
    }

    /**
     * Is this request bound for a route registered by Mcp::web()?
     *
     * Asks the package's registrar rather than pattern-matching the path, so the
     * answer stays correct if an MCP server is ever mounted somewhere other than
     * /mcp, and so unrelated routes that merely happen to live under /mcp are
     * not decorated.
     *
     * This runs after $next() because global middleware executes before routing:
     * $request->route() is only populated once the router has dispatched.
     */
    private function isMcpServerRoute(Request $request): bool
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return false;
        }

        return Mcp::getWebServer($route->uri()) !== null;
    }
}
