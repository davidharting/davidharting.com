# What the Claude.ai web connector requires of a remote MCP server's OAuth

Research for [#182](https://github.com/davidharting/davidharting.com/issues/182), a child of the
wayfinder map [#180](https://github.com/davidharting/davidharting.com/issues/180).

Researched 2026-08-23. Target: an OAuth-protected MCP server at `/mcp/admin` on this Laravel app,
using `laravel/mcp` (currently pinned `^0.8.2`) + Laravel Passport.

## How to read this document

Every claim is tagged with where it came from. The tags matter — parts of this are hard requirements
and parts are one person's bug report.

| Tag             | Meaning                                                                                                                                                                                   |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **[SPEC]**      | Model Context Protocol specification, normative language quoted.                                                                                                                          |
| **[ANTHROPIC]** | Anthropic's own connector documentation at `claude.com/docs/connectors/...`. This is first-party documentation of Claude's actual client behavior, and it is more specific than the spec. |
| **[SOURCE]**    | Read directly out of the `laravel/mcp` source at a specific tag. Verifiable.                                                                                                              |
| **[REPORT]**    | A third-party bug report or issue thread. Evidence that someone hit something, not proof of current behavior.                                                                             |
| **[UNKNOWN]**   | Could not be determined from primary sources. Includes how to find out.                                                                                                                   |

Anthropic's documentation is the acceptance gate here, not the MCP spec. Where they differ, Claude's
documented behavior wins — and it does differ in places. Anthropic says so explicitly:

> Claude's auth support differs in a few places from the generic MCP specification, so read this page
> even if you're already familiar with MCP auth.
> — [ANTHROPIC] [Authentication for connectors](https://claude.com/docs/connectors/building/authentication)

## Answers, in short

| Question               | Answer                                                                                                                                                                                                                                                                          | Confidence                                   |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| Is DCR required?       | **No.** Pre-registered client credentials are a first-class, documented option, entered in the custom-connector dialog.                                                                                                                                                         | [ANTHROPIC] High                             |
| Discovery order        | `WWW-Authenticate: resource_metadata` first; fallback probes `/.well-known/oauth-protected-resource/<mcp-path>` then `/.well-known/oauth-protected-resource`. Then AS metadata: `/.well-known/oauth-authorization-server`, falling back to `/.well-known/openid-configuration`. | [ANTHROPIC] High                             |
| Redirect URI           | `https://claude.ai/api/mcp/auth_callback`, single and documented for all hosted surfaces. Stability is undocumented but it has been stable in reports since at least May 2026.                                                                                                  | [ANTHROPIC] High / stability [REPORT] Medium |
| HTTPS + reachability   | Must be public HTTPS, resolving only to globally-routable IPv4. `http://localhost` is **never** usable for the web connector.                                                                                                                                                   | [ANTHROPIC] High                             |
| PKCE                   | S256 sent on **every** authorization request. AS metadata must advertise `code_challenge_methods_supported: ["S256"]`.                                                                                                                                                          | [ANTHROPIC] High                             |
| Scopes                 | Claude does not care _what_ the scopes are. It requests whatever you advertise. A single `mcp:use` scope is fine.                                                                                                                                                               | [ANTHROPIC] High                             |
| Token refresh          | Supported and expected. Refresh happens reactively on 401 plus proactively ~5 min before expiry. Return `invalid_grant` on a dead refresh token.                                                                                                                                | [ANTHROPIC] High                             |
| `laravel/mcp` friction | Two confirmed defects. The DCR `client_name` bug is fixed in 0.8.2; the missing `WWW-Authenticate` header is **unfixed in every released version** as of today.                                                                                                                 | [SOURCE]+[REPORT] High                       |

---

## 1. Is dynamic client registration required?

**No. A client can be pre-registered out of band.**

Anthropic documents six authentication types, of which DCR is only one. [ANTHROPIC]
[Authentication for connectors § Supported authentication types](https://claude.com/docs/connectors/building/authentication):

| Type                    | Description                                                  | Availability                       |
| ----------------------- | ------------------------------------------------------------ | ---------------------------------- |
| `oauth_dcr`             | OAuth 2.0 with Dynamic Client Registration (RFC 7591)        | Supported out of the box           |
| `oauth_cimd`            | OAuth 2.0 with Client ID Metadata Document                   | Supported out of the box           |
| `oauth_anthropic_creds` | OAuth 2.0 with Anthropic-held client credentials             | Contact `mcp-review@anthropic.com` |
| `custom_connection`     | Custom URL or credentials supplied at connection time        | Contact `mcp-review@anthropic.com` |
| `static_headers`        | Fixed credential entered by an org admin as a request header | Beta                               |
| `none`                  | No authentication (authless server)                          | Supported                          |

For a **custom connector added by URL** — which is exactly our case — pre-registration is directly
supported in the UI and needs no coordination with Anthropic:

> When a user adds a custom connector by URL, the OAuth Client Secret field is **optional**. Supply it
> only if your authorization server requires confidential-client authentication.
>
> Supplying your own pre-registered client ID (and secret, if your server requires one) as static
> client credentials is a good option when you want a stable OAuth client per organization: it avoids
> dynamic client registration entirely, and the credentials are scoped to the organization that
> entered them.
> — [ANTHROPIC] [Authentication § Custom connectors](https://claude.com/docs/connectors/building/authentication#custom-connectors)

The troubleshooting page states the requirement as a disjunction, confirming any one of the three
suffices:

> Claude needs one of: RFC 7591 dynamic client registration (a `registration_endpoint` in your
> authorization server metadata), Client ID Metadata Documents (`"client_id_metadata_document_supported": true`),
> or a pre-registered client. Without any of these, Claude can't obtain a client identity.
> — [ANTHROPIC] [Troubleshooting § No way to register a client](https://claude.com/docs/connectors/building/troubleshooting)

The spec agrees, and in fact **demotes** DCR in the newest revision. [SPEC]
[2025-11-25 authorization](https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization):

> 2. Authorization servers and MCP clients **SHOULD** support OAuth Client ID Metadata Documents […]
> 3. Authorization servers and MCP clients **MAY** support the OAuth 2.0 Dynamic Client Registration
>    Protocol (RFC7591).

with client priority order:

> 1. Use pre-registered client information for the server if the client has it available
> 2. Use Client ID Metadata Documents if the Authorization Server indicates […]
> 3. Use Dynamic Client Registration as a fallback […]
> 4. Prompt the user to enter the client information if no other option is available

For comparison, the older [SPEC] [2025-06-18 revision](https://modelcontextprotocol.io/specification/2025-06-18/basic/authorization)
said "Authorization servers and MCP clients **SHOULD** support the OAuth 2.0 Dynamic Client Registration
Protocol". Even there it was SHOULD, never MUST.

**Practical note for a personal, single-user server.** Anthropic actively recommends _against_ DCR at
scale, because it mints a new client row per connection:

> For servers expecting high traffic from the directory, prefer **CIMD or `oauth_anthropic_creds` over
> DCR**. DCR causes Claude to register a new client on every fresh connection, which can result in
> very large numbers of registered clients on your authorization server.
> — [ANTHROPIC] [Authentication § DCR and CIMD details](https://claude.com/docs/connectors/building/authentication#dcr-and-cimd-details)

For an admin server with one user, pre-registering a single Passport client and pasting its ID into
the connector dialog avoids exposing a public `POST /oauth/register` on the site entirely. That is
worth weighing on the map: `laravel/mcp`'s `Mcp::oauthRoutes()` registers an **unauthenticated,
publicly reachable** registration endpoint that creates a Passport client row per call. [SOURCE]
`src/Server/Registrar.php` @ v0.8.2 registers `Router::post($oauthPrefix.'/register', OAuthRegisterController::class)`
with no middleware and no throttle.

---

## 2. Discovery documents: which, in what order, and the `WWW-Authenticate` header

### The 401 is mandatory and load-bearing

> **Always return a `401` with a `WWW-Authenticate` header** whose `resource_metadata` parameter points
> at your protected resource metadata document […]
>
> ```http
> HTTP/1.1 401 Unauthorized
> WWW-Authenticate: Bearer resource_metadata="https://mcp.example.com/.well-known/oauth-protected-resource"
> ```
>
> The `401` status is required — Claude does not honor a `WWW-Authenticate` header on a `200` response
> […]
> — [ANTHROPIC] [Authentication § Cross-host authorization servers](https://claude.com/docs/connectors/building/authentication#cross-host-authorization-servers)

A `200` wrapping a JSON-RPC error does **not** trigger auth:

> A `200` with `isError: true` is an application-level tool failure. Claude passes the error text to the
> model as the tool result and moves on — there is no auth prompt. Only a transport-level `401` causes
> Claude to pause the call, run the OAuth flow, and retry.
> — [ANTHROPIC] [Lazy authentication § Return 401, not a tool error](https://claude.com/docs/connectors/building/lazy-authentication)

`403` is a terminal error _unless_ it carries `WWW-Authenticate: Bearer error="insufficient_scope"`,
which triggers scope step-up. [ANTHROPIC] same page.

### Order of fetches

1. **`WWW-Authenticate` on the 401.** Claude reads the `resource_metadata` parameter and fetches that
   URL. It does not have to be on the MCP server's origin — any HTTPS location serving the JSON works.
   [ANTHROPIC]
2. **Fallback probe, only if step 1 gave no pointer.** [ANTHROPIC] [Authentication](https://claude.com/docs/connectors/building/authentication#cross-host-authorization-servers):

    > If your `401` doesn't include a `resource_metadata` pointer, Claude can still infer the metadata
    > location by probing your MCP server's origin: `/.well-known/oauth-protected-resource/<your-mcp-path>`
    > first, then `/.well-known/oauth-protected-resource`. Treat this as a fallback — it only works when
    > your platform serves `/.well-known/*` paths, and it adds round-trips to every connection.

    Path-nested first, root second. This matches [SPEC] 2025-11-25, which makes the same ordering
    normative for clients ("they **MUST** fall back to constructing and requesting the well-known URIs
    in the order listed above").

3. **Authorization server metadata.** Claude takes the **first** entry of `authorization_servers` and
   fetches its discovery document:

    > For authorization server metadata, your server only needs to answer **one** of the two discovery
    > endpoints — Claude tries `/.well-known/oauth-authorization-server` (RFC 8414) first, then falls
    > back to `/.well-known/openid-configuration` (OpenID Connect Discovery 1.0). A `404` on one is
    > expected if the other returns `200`.
    > — [ANTHROPIC] [Troubleshooting § OAuth discovery fails](https://claude.com/docs/connectors/building/troubleshooting)

### Constraints on the documents themselves

[ANTHROPIC] [Authentication § Cross-host authorization servers](https://claude.com/docs/connectors/building/authentication#cross-host-authorization-servers), verbatim:

> - The protected resource metadata document's `resource` field must match your MCP server URL exactly
>   as the user enters it in Claude, including any path component.
> - The metadata's `authorization_servers` field must list your authorization server's issuer URL. If
>   you list more than one, **Claude uses the first entry and does not fall back to later entries** —
>   list your primary issuer first.
> - Your authorization server must serve its own discovery metadata […] and that host must also be
>   reachable from Anthropic's published egress range.

The AS metadata must also advertise `"code_challenge_methods_supported": ["S256"]` — see §5.

### Discovery caching

> Claude caches the discovery documents […] **globally, keyed by URL**, with a staleness window of about
> five minutes by default. All Claude users connecting to the same server URL share a single cache
> entry, and distinct server URLs (for example, staging versus production) cache independently.
> — [ANTHROPIC] [Lazy authentication § OAuth discovery caching](https://claude.com/docs/connectors/building/lazy-authentication#oauth-discovery-caching)

Practical consequence for iteration: after changing `scopes_supported` or any metadata field, expect
up to ~5 minutes before Claude picks it up. Do not conclude a metadata fix failed until that window has
passed. A PR preview URL is a distinct URL and therefore a distinct cache entry.

### Timeouts

> Claude waits up to **10 seconds** for a response from your OAuth discovery, registration, and token
> endpoints, and up to **30 seconds** for refresh token requests.
> — [ANTHROPIC] [Authentication § Endpoint latency](https://claude.com/docs/connectors/building/authentication#endpoint-latency)

---

## 3. Redirect URI

**One URI, for every hosted Claude surface:**

```
https://claude.ai/api/mcp/auth_callback
```

> For the hosted Claude surfaces (Claude.ai web, Desktop, mobile, and Cowork), register the following
> redirect URI: `https://claude.ai/api/mcp/auth_callback`
> — [ANTHROPIC] [Authentication § Callback URLs](https://claude.com/docs/connectors/building/authentication#callback-urls)

Claude Code is different and irrelevant to this ticket's acceptance gate: it is a native client using an
RFC 8252 loopback redirect on an ephemeral port, declaring `http://localhost/callback` and
`http://127.0.0.1/callback` in its
[Client ID Metadata Document](https://claude.ai/oauth/claude-code-client-metadata), which the
authorization server must match **port-agnostically**. [ANTHROPIC] same section. If we ever want the
same server usable from Claude Code, Passport's exact-match redirect validation becomes a problem worth
its own ticket.

**Stability.** Anthropic documents a single literal URI with no versioning or rotation notice.
[ANTHROPIC] High confidence that it is current. It is _not_ documented as a stability guarantee.
Corroborating evidence that it has held for months: a real observed authorization URL in
[laravel/mcp#210](https://github.com/laravel/mcp/issues/210) (2026-05-08) shows
`redirect_uri=https%3A%2F%2Fclaude.ai%2Fapi%2Fmcp%2Fauth_callback`. [REPORT] Medium.

Note that with DCR this barely matters — Claude supplies its redirect URI at registration time, so it
self-heals if Anthropic ever changes it. With a **pre-registered** Passport client, the URI is baked
into our client row, and a change would silently break the connector until we edit it. That is the real
tradeoff between the two registration modes for us.

One incidental but useful fact: the callback endpoint **only accepts GET**. An Anthropic collaborator
confirmed this on
[anthropics/claude-ai-mcp#313](https://github.com/anthropics/claude-ai-mcp/issues/313), where a
Next.js server issued a 307 method-preserving redirect and the browser POSTed to the callback.
[REPORT/ANTHROPIC-staff] High. Not a risk for us: Passport's authorize endpoint issues a 302.

---

## 4. HTTPS and public reachability. Is `http://localhost` ever acceptable?

**For the Claude.ai web connector: no. Never.** This is the clearest-cut answer in the whole document.

Claude.ai connectors run on Anthropic's infrastructure, not the user's machine, and Claude validates DNS
before any HTTP request leaves its network. [ANTHROPIC]
[Troubleshooting § Hostname resolves to a private IP](https://claude.com/docs/connectors/building/troubleshooting):

> Before making any request, Claude resolves your server's hostname and validates the result. If **any**
> resolved address is not globally routable, Claude rejects the connection before any HTTP request
> leaves Anthropic's network.
>
> Claude rejects the connection when the hostname:
>
> - resolves to a private address (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`)
> - resolves to a carrier-grade NAT address (`100.64.0.0/10`)
> - **resolves to a loopback or link-local address**
> - resolves to a mix of public and non-public addresses — every returned address must be globally routable
> - has no `A` record from public DNS — **connectors are IPv4-only**, so a hostname that only publishes
>   `AAAA` records can't be reached

And explicitly for local development:

> Claude reaches custom connectors from Anthropic's infrastructure, so `localhost` is not reachable
> directly. Expose the server over a public HTTPS tunnel (for example,
> `cloudflared tunnel --url http://localhost:3000` or `ngrok http 3000`), then in **Customize >
> Connectors**, select **Add custom connector** and enter the tunnel's `/mcp` URL.
> — [ANTHROPIC] [Lazy authentication § Try it](https://claude.com/docs/connectors/building/lazy-authentication)

This is the one place where Claude Code and Claude.ai genuinely diverge, and it explains a very common
false signal:

> **Works in Claude Code or `curl` but not claude.ai.** The CLI and `curl` connect from your machine,
> while claude.ai connects from Anthropic's servers.
> — [ANTHROPIC] Troubleshooting

**Do not use `php artisan mcp:inspector` or Claude Code passing as evidence that the Claude.ai
connector will work.** They exercise a different network path and a different client.

Other reachability constraints:

- **HTTPS.** The directory submission portal states the server URL "must be `https://`". [ANTHROPIC]
  [Submission](https://claude.com/docs/connectors/building/submission). The MCP spec independently
  requires "All authorization server endpoints **MUST** be served over HTTPS" and "All redirect URIs
  **MUST** be either `localhost` or use HTTPS". [SPEC] Both revisions. There is no documented HTTPS
  carve-out for custom connectors; every example and diagnostic uses `https://`.
- **No cross-host redirects on the MCP URL.** [ANTHROPIC] Troubleshooting § 3: a 301/302/307/308 to a
  different host causes the `Authorization` header to be dropped, and the connector fails later with
  "Authorization with the MCP server failed". Register the URL the server actually listens on. Relevant
  to us: Cloudflare in front of Render, and any apex↔`www` canonicalisation.
- **Egress range.** Anthropic's outbound traffic originates from `160.79.104.0/21`. [ANTHROPIC]
  Authentication § Network reference. Both the MCP host and the authorization-server host must accept
  it. For us they are the same host, so one Cloudflare WAF rule covers both — but Cloudflare bot
  management is a plausible failure mode, surfacing as unexplained 403/429 in edge logs.
- **Origin header.** "Overly strict `Origin`-header validation rejecting Anthropic's requests" is listed
  as a common cause of `initialize` timeouts. [ANTHROPIC]
  [Testing § Debugging](https://claude.com/docs/connectors/building/testing).

**Testing path for us:** PR preview environments (`https://davidhartingdotcom-web-pr-N.onrender.com`) are
public HTTPS with real DNS, so they are a legitimate acceptance-test surface for the connector — better
than a tunnel, and each preview URL gets its own discovery cache entry.

---

## 5. PKCE, scopes, and token refresh

### PKCE — mandatory, S256, unconditional

> Claude includes a PKCE `code_challenge` with `code_challenge_method=S256` on every authorization
> request, regardless of which registration mechanism it uses. Your authorization server must support
> S256 PKCE, and the MCP authorization spec requires it to advertise
> `"code_challenge_methods_supported": ["S256"]` in its metadata so spec-compliant clients can verify
> support before starting the flow.
> — [ANTHROPIC] [Authentication § DCR and CIMD details](https://claude.com/docs/connectors/building/authentication#dcr-and-cimd-details)

[SPEC] 2025-11-25 hardens this into a client-side abort condition: "If `code_challenge_methods_supported`
is absent, the authorization server does not support PKCE and MCP clients **MUST** refuse to proceed."

Passport supports S256 PKCE, and `laravel/mcp` already advertises it. [SOURCE] `Registrar::authorizationServerMetadata()`
emits `'code_challenge_methods_supported' => ['S256']`. Confirmed live in the wild: the authorization URL
in [laravel/mcp#210](https://github.com/laravel/mcp/issues/210) carries
`code_challenge=…&code_challenge_method=S256`. [REPORT]

### Scopes — Claude is content-agnostic

Claude does not interpret scope strings, does not require particular names, and does not require more
than one. It has a documented selection ladder:

> To control which scopes Claude requests, include a `scope` parameter in the `WWW-Authenticate` header
> on your `401` response. If you don't, Claude requests the scopes your protected resource metadata
> advertises in `scopes_supported`. Claude also appends `offline_access` when your authorization server
> metadata lists it in `scopes_supported`, to obtain a refresh token.
> — [ANTHROPIC] [Authentication § DCR and CIMD details](https://claude.com/docs/connectors/building/authentication#dcr-and-cimd-details)

So: **`laravel/mcp`'s single hardcoded `mcp:use` scope is fine.** Claude will read
`scopes_supported: ["mcp:use"]` from the PRM and request exactly `mcp:use`. Confirmed in the wild —
[laravel/mcp#210](https://github.com/laravel/mcp/issues/210)'s observed authorize URL ends
`&scope=mcp%3Ause`. [REPORT] Medium-high.

Because `laravel/mcp` does not list `offline_access` in `scopes_supported`, Claude will **not** append
it. [SOURCE] That is fine — Passport's authorization-code grant issues a refresh token without an
`offline_access` scope, and `laravel/mcp` advertises `grant_types_supported: ["authorization_code", "refresh_token"]`.

Laravel's own docs are candid that scopes are vestigial here:

> In this scenario, we're simply using OAuth as a translation layer to the underlying authenticatable
> model. We are ignoring many aspects of OAuth, such as scopes. Laravel MCP, via the `Mcp::oauthRoutes`
> method […] adds, advertises, and uses a single `mcp:use` scope.
> — [SOURCE] [Laravel MCP docs](https://laravel.com/docs/13.x/mcp)

That is compatible with Claude. It does mean scope **step-up** (403 + `insufficient_scope`) is not
available to us — irrelevant for a single admin server, but worth knowing if `/mcp/admin` ever needs
graduated permissions.

### Token refresh

> Claude refreshes tokens **reactively on a 401 response**, with a proactive refresh up to five minutes
> before the stored expiry. To avoid refresh failures:
>
> - Return RFC 6749-compliant error codes (`invalid_grant`, not `invalid_request` or a custom code) when
>   a refresh token is no longer valid
> - Rotate refresh tokens for public-client connections. DCR and CIMD register Claude as a public client
>   […] If you rotate, return the new refresh token in the same response that invalidates the old one.
>
> Your `/token` endpoint must accept `Content-Type: application/x-www-form-urlencoded` […] Dynamic
> client registration (`/register`) uses `application/json` per RFC 7591 section 3.1, so don't assume
> the same parser works for both.
> — [ANTHROPIC] [Authentication § Token refresh](https://claude.com/docs/connectors/building/authentication#token-refresh)

Passport (league/oauth2-server) satisfies all of this out of the box: form-encoded `/oauth/token`,
`invalid_grant` on dead refresh tokens, and refresh-token rotation. No action expected, but this is
worth a smoke test after the token TTL elapses — refresh is the failure mode that only shows up days
later.

### Audience / RFC 8707 `resource`

> Claude sends the RFC 8707 `resource` parameter on authorization and token requests, set to the
> canonical form of your MCP server URL — lowercase scheme and host, no trailing slash, no fragment, no
> default port — including any path component. Your authorization server should issue tokens with that
> audience, and your MCP server should accept the canonical value when checking `aud` rather than doing
> a strict byte-for-byte comparison against what the user typed.
> — [ANTHROPIC] [Troubleshooting § Audience mismatch](https://claude.com/docs/connectors/building/troubleshooting)

This is a **[SPEC] MUST for the server** (validate that the token was issued for you), and Passport does
not implement resource indicators. Claude does not appear to enforce that we honour it — it is our
security obligation, not a connection gate. Since our server is the authorization server, cross-audience
token confusion is a narrow risk, but it should be named in the plan rather than silently skipped.
See [UNKNOWN-2].

---

## 6. Known friction between Claude.ai and `laravel/mcp`

`laravel/mcp` v0.8.2's emitted metadata, read from
[`src/Server/Registrar.php` @ v0.8.2](https://github.com/laravel/mcp/blob/v0.8.2/src/Server/Registrar.php).
[SOURCE]

Protected resource metadata (`/.well-known/oauth-protected-resource/{path}`):

```json
{
    "resource": "https://davidharting.com/mcp/admin",
    "authorization_servers": ["https://davidharting.com"],
    "scopes_supported": ["mcp:use"]
}
```

Authorization server metadata (`/.well-known/oauth-authorization-server`):

```json
{
    "issuer": "https://davidharting.com",
    "authorization_endpoint": "https://davidharting.com/oauth/authorize",
    "token_endpoint": "https://davidharting.com/oauth/token",
    "registration_endpoint": "https://davidharting.com/oauth/register",
    "response_types_supported": ["code"],
    "code_challenge_methods_supported": ["S256"],
    "scopes_supported": ["mcp:use"],
    "grant_types_supported": ["authorization_code", "refresh_token"]
}
```

Checked field by field against Anthropic's requirements, this **passes**. Notably it advertises S256,
a registration endpoint, and a single issuer.

### FRICTION 1 — `WWW-Authenticate` is never emitted on an auth-protected route. **Unfixed in every released version.**

[SOURCE]+[REPORT] High confidence. [laravel/mcp#278](https://github.com/laravel/mcp/issues/278),
filed 2026-07-31 against v0.9.1.

`Registrar::web()` attaches `AddWwwAuthenticateHeader` as **route** middleware, and the middleware
decorates a _returned_ 401. But Laravel sorts `Illuminate\Auth\Middleware\Authenticate` to the front of
the stack (it implements `AuthenticatesRequests`, which is in the framework's `$middlewarePriority`
list), so `auth:api` produces the 401 at the outermost layer and the decorator never sees it. The
resolved order for `Mcp::web('/mcp', S::class)->middleware(['auth:api'])` is:

```
0. Illuminate\Auth\Middleware\Authenticate:api
1. Illuminate\Routing\Middleware\SubstituteBindings
2. Laravel\Mcp\Server\Middleware\ReorderJsonAccept
3. Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader   <-- inside auth
```

Result: a bare `401` with **no** `WWW-Authenticate` header. The header only works in configurations that
do not need it.

**Fix status.** [PR #322 "Expose OAuth challenges on authenticated MCP routes"](https://github.com/laravel/mcp/pull/322)
was merged to `main` on **2026-08-17**, registering the middleware globally via
`$kernel->pushMiddleware(AddWwwAuthenticateHeader::class)` in `McpServiceProvider::registerGlobalMiddleware()`.
Verified present on `main`, absent from v0.8.2. [SOURCE]

The most recent tags are `v0.9.4` (2026-08-16) and `v1.0.0-beta.1` (2026-08-14) — **both predate the
merge**. As of 2026-08-23 **no released version of `laravel/mcp` contains this fix**, and this repo is
pinned to `^0.8.2`, two minors behind besides.

**How bad is it for us, actually?** Less bad than it sounds. Claude's documented fallback probes
`/.well-known/oauth-protected-resource/<mcp-path>` first, and `laravel/mcp` serves that route correctly
with the right `resource` value. So discovery should still succeed — Anthropic calls the probe a
supported fallback, not an error path. But it adds round-trips on every connection, it is fragile, and it
diverges from a [SPEC] MUST. The issue author makes the same observation: "`/.well-known/oauth-protected-resource/{path}`
keeps serving correctly, so nothing indicates the challenge is missing."

**Workaround available today**, from the issue, in `bootstrap/app.php` (or `Kernel::$middlewarePriority`
for this app's Laravel 10 structure):

```php
$middleware->prependToPriorityList(
    before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
    prepend: \Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader::class,
);
```

### FRICTION 2 — DCR rejected Claude's registration for want of `client_name`. **Fixed in 0.8.2.**

[REPORT]+[SOURCE] High confidence. [laravel/mcp#253](https://github.com/laravel/mcp/issues/253),
filed 2026-06-24 against v0.7.2, titled "OAuth DCR rejects registrations without `client_name`,
contrary to RFC 7591 (breaks Claude connector)".

`OAuthRegisterController` required `client_name` or `name` via `required_without`, but RFC 7591 §2 makes
`client_name` OPTIONAL. Claude's DCR request was rejected `400 invalid_client_metadata`, surfacing to
users as _"Couldn't register with … sign-in service"_.

Fixed by [#254](https://github.com/laravel/mcp/pull/254), released in **v0.8.2** (2026-06-26). Verified
in source: v0.8.2's controller has `'client_name' => ['nullable', ...]` with no `required_without`, and a
`resolveClientName()` fallback to the first redirect URI's host, then `'MCP Client'`. [SOURCE] **Our
pinned version already has this.** Do not downgrade below 0.8.2.

### FRICTION 3 — `redirect_domains` allowlist gate on DCR

[SOURCE] High confidence, low severity. `OAuthRegisterController` validates every submitted
`redirect_uris` entry against `config('mcp.redirect_domains')`. The shipped default is `['*']` (allow
all), so an unpublished config works. But if we publish and tighten `config/mcp.php` — which we should,
given the endpoint is public and unauthenticated — `https://claude.ai` must be on the list or Claude's
DCR gets `400 invalid_redirect_uri`. This repo has **not** published `config/mcp.php` today.

### FRICTION 4 — unresolved reports, cause unknown

- [laravel/mcp#210 "Claude can't connect using OAuth"](https://github.com/laravel/mcp/issues/210)
  (v0.7, 2026-05-08). Claude reached `/oauth/authorize`, the user approved, and Claude then failed with
  `error_code=mcp_token_exchange_failed`. MCP Inspector completed the same flow fine. The reporter was
  using a **manually pre-registered** public Passport client with client ID `5` typed into Claude's
  advanced settings, and later suspected the Passport UUID-vs-integer client-ID change. **Never
  reproduced; closed without a root cause.** [REPORT] Low confidence as a general claim, but it is a
  direct warning shot at the pre-registered-client path, which is the path §1 otherwise recommends.
- [anthropics/claude-ai-mcp#449](https://github.com/anthropics/claude-ai-mcp/issues/449) — titled
  "Custom Connector OAuth Connection Failure - Laravel Passport MCP Server" and therefore tempting to
  cite, but an Anthropic collaborator diagnosed it as a **TLS-level reset before any HTTP request**, i.e.
  a WAF/mTLS reachability problem, with Passport never involved. Not a `laravel/mcp` issue. Included
  here only so nobody re-finds it and misreads it.

I found **no** Anthropic-documented friction specific to `laravel/mcp`. Anthropic's docs do not mention
Laravel or Passport anywhere.

---

## 7. Open unknowns

**[UNKNOWN-1] Does Claude currently send the RFC 8707 `resource` parameter, and does Passport tolerate
it?** Anthropic's troubleshooting page says Claude sends it. But the one real captured authorization URL
we have ([laravel/mcp#210](https://github.com/laravel/mcp/issues/210), 2026-05) contains
`response_type`, `client_id`, `redirect_uri`, `code_challenge`, `code_challenge_method`, `state`, and
`scope` — and **no `resource`**. That capture predates the 2025-11-25 spec revision and may simply be
stale or trimmed by the reporter. league/oauth2-server ignores unrecognised authorization parameters, so
the expected outcome is "harmless, and tokens carry no `aud`" — but that is inference, not a verified
fact.
_How to find out:_ stand up `/mcp/admin` on a PR preview, log the full query string on
`/oauth/authorize` and the full POST body on `/oauth/token`, and connect from Claude.ai. One connection
answers it definitively.

**[UNKNOWN-2] Whether Claude blocks the connection if tokens carry no `aud` matching the `resource`.**
The [SPEC] makes audience validation a server-side MUST; nothing in Anthropic's docs says Claude
verifies our compliance. Anthropic's "audience mismatch" troubleshooting entry describes servers
_rejecting_ correctly-issued tokens, which is the opposite failure. Best read: not a connection gate, but
a real security gap we are choosing to accept.
_How to find out:_ the same single connection test as UNKNOWN-1.

**[UNKNOWN-3] Whether Claude honours the per-client `token_endpoint_auth_method: "none"` from the DCR
response, or the AS-metadata default.** `laravel/mcp` does **not** emit
`token_endpoint_auth_methods_supported` in its authorization-server metadata [SOURCE], and RFC 8414
says the default when omitted is `["client_secret_basic"]`. The DCR _response_ does return
`"token_endpoint_auth_method": "none"` and Passport creates a public (non-confidential) client. RFC 7591
makes the registration response authoritative for that client, so this should work — and Anthropic's
docs only require `"none"` in the AS metadata for the **CIMD** path, not the DCR path. Still, an omitted
metadata field defaulting to something we don't support is exactly the shape of a silent token-exchange
failure, and #210's `mcp_token_exchange_failed` is consistent with it.
_How to find out:_ same test; inspect whether Claude's POST to `/oauth/token` includes an
`Authorization: Basic` header or a bare `client_id` in the form body.

**[UNKNOWN-4] Long-term stability of `https://claude.ai/api/mcp/auth_callback`.** Documented as current;
no stability guarantee published. _How to reduce exposure:_ prefer DCR (Claude supplies its own redirect
URI each time) or accept a one-line Passport client edit if it ever changes.

**[UNKNOWN-5] Exact release that will carry the #322 `WWW-Authenticate` fix.** Merged to `main`
2026-08-17; no tag cut since. _How to find out:_ watch
[laravel/mcp releases](https://github.com/laravel/mcp/releases) for the next tag after 2026-08-17.

---

## 8. What this means for the `/mcp/admin` plan

Stated as implications, not decisions — the map owns the decisions.

1. **Acceptance testing must happen on a public HTTPS URL.** A PR preview environment is the natural fit.
   `mcp:inspector`, `curl`, and Claude Code all exercise a different network path and different client
   code, and none of them is evidence the Claude.ai connector works. (§4)
2. **Pre-registering a single Passport client is legitimate and avoids exposing a public
   `POST /oauth/register`.** But it is the path with the one unreproduced failure report attached
   (#210), and it hard-codes Claude's callback URL into our database. DCR is the better-trodden path.
   Whichever we pick, the other is a cheap fallback: it is a setting in the connector dialog. (§1, §3)
3. **Apply the `AddWwwAuthenticateHeader` priority workaround.** No released `laravel/mcp` emits the
   header on an `auth:api`-protected route. Discovery will probably still work through Claude's
   documented well-known fallback, but the fix is three lines and removes a whole class of ambiguity
   from any later debugging session. (§6, FRICTION 1)
4. **Stay on `laravel/mcp` >= 0.8.2**, and consider moving to 0.9.x — 0.8.2 fixes the DCR `client_name`
   bug that literally broke the Claude connector, and 0.9.x is where upstream fixes are landing. (§6,
   FRICTION 2)
5. **If we publish `config/mcp.php`, allowlist `https://claude.ai` in `redirect_domains`.** (§6,
   FRICTION 3)
6. **`mcp:use` as the only scope is fine.** No change needed, and scope step-up is simply unavailable.
   (§5)
7. **Watch out for Cloudflare.** Anthropic egresses from `160.79.104.0/21`; bot management or a WAF rule
   in front of `davidharting.com` would surface as "Couldn't reach the MCP server" with nothing in the
   Laravel log. Also confirm `/mcp/admin` does not 3xx to a different host. (§4)
8. **Budget ~5 minutes between a metadata change and Claude noticing.** Global URL-keyed discovery
   cache. (§2)

---

## Sources

Primary — Anthropic first-party connector documentation:

- [Building custom connectors](https://claude.com/docs/connectors/building)
- [Authentication for connectors](https://claude.com/docs/connectors/building/authentication)
- [Lazy authentication for MCP servers](https://claude.com/docs/connectors/building/lazy-authentication)
- [Troubleshooting connectors](https://claude.com/docs/connectors/building/troubleshooting)
- [Testing your connector](https://claude.com/docs/connectors/building/testing)
- [Third party connectors with remote MCP](https://claude.com/docs/connectors/custom/remote-mcp)
- [Submitting to the Connectors Directory](https://claude.com/docs/connectors/building/submission)
- [Get started with custom connectors using remote MCP](https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp) (help centre)

Primary — Model Context Protocol specification:

- [Authorization, 2025-11-25](https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization)
- [Authorization, 2025-06-18](https://modelcontextprotocol.io/specification/2025-06-18/basic/authorization)

Primary — source code:

- [`laravel/mcp` `src/Server/Registrar.php` @ v0.8.2](https://github.com/laravel/mcp/blob/v0.8.2/src/Server/Registrar.php)
- [`laravel/mcp` `src/Server/Middleware/AddWwwAuthenticateHeader.php` @ v0.8.2](https://github.com/laravel/mcp/blob/v0.8.2/src/Server/Middleware/AddWwwAuthenticateHeader.php)
- [`laravel/mcp` `src/Server/Http/Controllers/OAuthRegisterController.php` @ v0.8.2](https://github.com/laravel/mcp/blob/v0.8.2/src/Server/Http/Controllers/OAuthRegisterController.php)
- [`laravel/mcp` `config/mcp.php` @ v0.8.2](https://github.com/laravel/mcp/blob/v0.8.2/config/mcp.php)
- [`laravel/mcp` `src/Server/McpServiceProvider.php` @ main](https://github.com/laravel/mcp/blob/main/src/Server/McpServiceProvider.php)
- [Laravel MCP documentation](https://laravel.com/docs/13.x/mcp)

Third-party reports (labelled as such above, not treated as authoritative):

- [laravel/mcp#278 — AddWwwAuthenticateHeader never runs when the MCP route is protected by auth middleware](https://github.com/laravel/mcp/issues/278)
- [laravel/mcp#322 — Expose OAuth challenges on authenticated MCP routes](https://github.com/laravel/mcp/pull/322)
- [laravel/mcp#253 — OAuth DCR rejects registrations without `client_name`](https://github.com/laravel/mcp/issues/253)
- [laravel/mcp#210 — Claude can't connect using OAuth](https://github.com/laravel/mcp/issues/210)
- [anthropics/claude-ai-mcp#313 — OAuth callback returns Method Not Allowed](https://github.com/anthropics/claude-ai-mcp/issues/313)
- [anthropics/claude-ai-mcp#449 — Laravel Passport MCP server connection failure (turned out to be TLS/WAF)](https://github.com/anthropics/claude-ai-mcp/issues/449)
