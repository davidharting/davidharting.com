---
name: passport-13-laravel-mcp-wiring
status: research
issue: https://github.com/davidharting/davidharting.com/issues/183
---

# How Passport 13 and `laravel/mcp` 0.8 wire together in this app

Research for [#183](https://github.com/davidharting/davidharting.com/issues/183), a child of wayfinder map [#180](https://github.com/davidharting/davidharting.com/issues/180). It establishes the facts the install ticket ([#185](https://github.com/davidharting/davidharting.com/issues/185)) depends on. **Nothing was installed and no application code was changed.**

## Method and trust levels

| Source                                      | Trust                                                                                                      | Used for                                            |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| `vendor/laravel/mcp` v0.8.2 (local)         | Highest — the exact code that will run                                                                     | Every `laravel/mcp` claim                           |
| `vendor/laravel/framework` v13.18.1 (local) | Highest                                                                                                    | Middleware pipeline, sorting, gates, `install:api`  |
| This repo's own code                        | Highest                                                                                                    | Guards, gate, `User`, `TrustProxies`, `render.yaml` |
| `github.com/laravel/passport` branch `13.x` | High — Passport is not installed here, so this is the published source rather than the resolved dependency | Passport internals, migrations, traits              |
| `laravel.com/docs/13.x/passport`            | High (first-party docs)                                                                                    | Install narrative, guard snippet                    |

Two claims were verified by _running code_ rather than by reading it; both scripts are reproduced inline below.

Version facts (`composer.json`, `composer.lock`): `laravel/framework` **v13.18.1**, `laravel/mcp` **v0.8.2**, `laravel/sanctum` **v4.3.2**, PHP platform pinned to **8.4.1**. Passport's current major is **13.x** (latest tag `v13.7.6`, 2026-08-11) and it requires `illuminate/* ^11.35|^12.0|^13.0`, so **Passport 13 supports Laravel 13** with no constraint gymnastics.

---

## Answers at a glance

| Question                                         | Answer                                                                                                            |
| ------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------- |
| `install:api --passport` vs `passport:install`?  | `install:api --passport` is mostly wrong for this app. Do the three real steps by hand.                           |
| `api` guard config                               | Four lines in `config/auth.php`. No `app/Http/Kernel.php` change needed for `auth:api`.                           |
| Does `can:administrate` compose with `auth:api`? | Yes. Verified. `auth:api` rebinds the default guard, and the gate resolves the Passport user through it.          |
| Publish `config/mcp.php`?                        | Not required. Publish it anyway to set `redirect_domains` (default is `*`).                                       |
| Set `authorization_server` explicitly?           | Not required — `TrustProxies` already yields `https://` behind Render. Setting it is still the cheaper guarantee. |
| Passport migrations                              | Five tables, published into `database/migrations/`. No Postgres blockers.                                         |
| `User` traits                                    | `Laravel\Passport\HasApiTokens` + `Laravel\Passport\Contracts\OAuthenticatable`.                                  |
| Sanctum conflict?                                | **Yes — a hard PHP fatal error.** Sanctum's trait must be _removed_, not kept alongside.                          |
| Is `AddWwwAuthenticateHeader` automatic?         | Yes. `Mcp::web()` applies it. Do not add it manually.                                                             |

---

## 1. What installation actually involves

### `php artisan install:api --passport` is a poor fit here

The command is `Illuminate\Foundation\Console\ApiInstallCommand` (`vendor/laravel/framework/src/Illuminate/Foundation/Console/ApiInstallCommand.php`). With `--passport` it does exactly four things:

1. `requireComposerPackages(..., ['laravel/passport:^13.0'])` — a `composer require` (lines 149-154).
2. Copies `stubs/api-routes.stub` to `routes/api.php`, **unless that file already exists**, in which case it prints `API routes file already exists.` and skips (lines 50-67).
3. Inside that same `else` branch only, `uncommentApiRoutesFile()` rewrites `bootstrap/app.php` (lines 93-116).
4. Runs `passport:install` as a subprocess (lines 69-76).

Two of those four are irrelevant or hazardous for this repo:

- **`routes/api.php` already exists here**, so step 2 short-circuits with an error message. Because step 3 lives inside the skipped branch, `bootstrap/app.php` is never touched. **This is lucky, not by design.** This repo uses the Laravel 10 structure: `bootstrap/app.php` builds an `Illuminate\Foundation\Application` and binds the HTTP/console kernels (verified — it contains no `web:`/`api:` route registration, and route files are registered by `app/Providers/RouteServiceProvider.php` instead). Had `routes/api.php` been absent, the command would have hit the `else` at line 111 and printed `Unable to automatically add API route definition` — harmless, but it would also have dropped a stub `routes/api.php` on top of the real one's slot.
- The `composer require` mutates `composer.json`, which #183 explicitly forbids and which the install ticket will want to do deliberately.

**Recommendation (confidence: high):** skip `install:api` entirely and run the three steps that matter.

### The three steps that matter

```shell
composer require laravel/passport:^13.0
php artisan vendor:publish --tag=passport-migrations
php artisan vendor:publish --tag=passport-config   # only if loading keys from env — see §5
php artisan passport:keys                           # writes storage/oauth-{private,public}.key
php artisan migrate
```

`passport:install` (`src/Console/InstallCommand.php` on `13.x`) is just a wrapper that calls `passport:keys`, then `vendor:publish --tag=passport-config`, then `vendor:publish --tag=passport-migrations`, then _interactively_ offers `migrate` and the creation of a **personal access grant client**. This app has no use for personal access tokens, and the prompts make it awkward in CI, so running the underlying commands directly is clearer. Either path is fine; the wrapper is not magic.

`Laravel\Passport\PassportServiceProvider` is auto-discovered (`extra.laravel.providers` in Passport's `composer.json`) and this repo's `config/app.php` uses `ServiceProvider::defaultProviders()->merge([...])` with an empty "Package Service Providers" block, so **no manual provider registration is needed**.

### Routes Passport registers by itself

`PassportServiceProvider::registerRoutes()` loads `routes/web.php` from the package under `'as' => 'passport.'`, `'prefix' => config('passport.path', 'oauth')`, `'middleware' => config('passport.middleware', [])` (default `[]`). That yields the two route names `Registrar` needs — `passport.authorizations.authorize` (`GET /oauth/authorize`, middleware `web`) and `passport.token` (`POST /oauth/token`, middleware `throttle`).

Notes worth carrying into the install ticket:

- `POST /oauth/token` is **not** in the `web` group, so it is not CSRF-protected. Correct — MCP clients post to it.
- `Mcp::oauthRoutes()` registers `POST oauth/register`. Passport's own routes are `token`, `authorize`, `device*`, `tokens`, `clients`, `scopes`, `personal-access-tokens`. **No path collision** on the shared `oauth` prefix.
- `Passport::$deviceCodeGrantEnabled` defaults to `true` (`src/Passport.php:36`), so `/oauth/device` and `/oauth/device/code` get registered too, and the client-credentials grant is always enabled (`PassportServiceProvider::registerAuthorizationServer()`). That is public surface this app does not need. `Passport::$registersJsonApiRoutes` similarly exposes `/oauth/clients`, `/oauth/tokens`, `/oauth/scopes` behind `auth:web`. Consider disabling what is unused. **Open decision, not a fact.**

---

## 2. The `api` guard in a Laravel 10-structure app

`config/auth.php` currently defines **only** a `web` guard (lines 40-45). The change the docs prescribe (<https://laravel.com/docs/13.x/passport#installation>) is purely a config edit and is structure-agnostic:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],

    'api' => [
        'driver' => 'passport',
        'provider' => 'users',
    ],
],
```

The `users` provider already exists and already points at `App\Models\User` (`config/auth.php:64-68`), so nothing else changes.

**The Laravel 10 structure costs nothing here.** The `auth` alias is already registered in `app/Http/Kernel.php:79` (`'auth' => App\Http\Middleware\Authenticate::class`) and `can` at line 83 (`Illuminate\Auth\Middleware\Authorize`). `auth:api` therefore works the moment the guard exists. `PassportServiceProvider::registerGuard()` calls `Auth::resolved(...)->extend('passport', ...)`, which is driver registration, not middleware registration — nothing to wire into `Kernel.php`.

The one place the structure _does_ matter: Passport's scope-checking middleware, `Laravel\Passport\Http\Middleware\CheckToken` and `CheckTokenForAnyScope`, are documented via their `::using()` static helpers rather than aliases, so they also need no `Kernel.php` entry. Only the SPA middleware `CreateFreshApiToken` is documented as a `bootstrap/app.php` append; if it is ever wanted here it goes in the `web` group in `app/Http/Kernel.php:55-62` instead. This app does not need it.

### Scope enforcement is not automatic

`auth:api` proves the token is valid. It does **not** check that the token carries `mcp:use`. `Registrar::oauthRoutes()` advertises `scopes_supported: ['mcp:use']` and `OAuthRegisterController` returns `'scope' => 'mcp:use'` on registration, but nothing on the resource route verifies it. If the install ticket wants that guarantee, it needs `CheckToken::using('mcp:use')` on the route. **Confidence: high** (read both `Registrar.php` and the Passport docs' "Checking Scopes" section).

---

## 3. Does `can:administrate` compose with `auth:api`? — Yes

The gate is defined in `app/Providers/AppServiceProvider.php:24-26`:

```php
Gate::define('administrate', function (User $user) {
    return $user->is_admin;
});
```

The chain works, for three reasons, all verified in the local framework source:

1. `Illuminate\Auth\Middleware\Authenticate::authenticate()` calls `$this->auth->shouldUse($guard)` on success (`Authenticate.php:83`). `AuthManager::shouldUse()` calls `setDefaultDriver($name)` **and** rebinds `$this->userResolver` (`AuthManager.php:206-213`).
2. The Gate singleton is constructed with `fn () => call_user_func($app['auth']->userResolver())` (`Illuminate\Auth\AuthServiceProvider::registerAccessGate()`, lines 57-62). It reads the resolver _at call time_, so it sees the `api` guard.
3. The `api` guard's user comes from `PassportUserProvider`, which delegates `retrieveById()` straight to the app's Eloquent provider (`src/PassportUserProvider.php`). That provider is configured with `model => App\Models\User`, so the object handed to the closure **is** an `App\Models\User`. The `User $user` type hint is safe.

`Illuminate\Auth\Middleware\Authorize::handle()` calls `$this->gate->authorize($ability, [])` with no model arguments, which matches the single-parameter closure.

### Verified: the priority sorter reorders this route's middleware

`Router::resolveMiddleware()` ends with `$this->sortMiddleware($middleware)` (`Router.php:881`), which runs `SortedMiddleware` against `$middlewarePriority`. This repo's `app/Http/Kernel.php` does **not** override `$middlewarePriority`, so the parent default applies (`Illuminate\Foundation\Http\Kernel.php:103-115`), and it lists `AuthenticatesRequests` (index 5), `ThrottleRequests` (6) and `Authorize` (10). The two `laravel/mcp` middleware are absent from that map.

Running the real sorter against the exact stack the phase-2 route would produce:

```php
// scratch script, run against this repo's vendor/autoload.php
$gathered = [
    Laravel\Mcp\Server\Middleware\ReorderJsonAccept::class,
    Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader::class,
    App\Http\Middleware\Authenticate::class.':api',
    Illuminate\Auth\Middleware\Authorize::class.':administrate',
    Illuminate\Routing\Middleware\ThrottleRequests::class.':60,1',
];
(new Illuminate\Routing\SortedMiddleware($defaultPriorityMap, $gathered))->all();
```

```
ReorderJsonAccept
AddWwwAuthenticateHeader
App\Http\Middleware\Authenticate:api
ThrottleRequests:60,1          <- moved ahead of Authorize
Authorize:administrate
```

Three consequences:

- `AddWwwAuthenticateHeader` stays **outside** `auth:api`, which is exactly what it needs (see §7).
- `can:administrate` ends up **after** the throttle, not where it was written. Harmless, but the route file will not read the way the pipeline runs.
- `auth:api` runs **before** the throttle regardless of the order written. So an unauthenticated flood of the admin route is rejected by auth without consuming the rate limiter. Whether that is desirable is a design call, but it is not what `->middleware(['auth:api', 'can:administrate', 'throttle:60,1'])` looks like it says.

### Exceptions still become responses in the right place

`Illuminate\Routing\Pipeline::handleException()` (lines 40-58) reports and _renders_ any throwable escaping a pipe, returning it as a response. So `AuthenticationException` from `auth:api` becomes a rendered 401 that flows back out through `AddWwwAuthenticateHeader` as a normal `Response`. The challenge header is attached. Same for `AuthorizationException` from the gate — but note that a gate denial renders **403**, and `AddWwwAuthenticateHeader` only acts on 401 (`AddWwwAuthenticateHeader.php:21-23`). A non-admin with a valid token gets a bare 403 and no discovery hint. That is the correct OAuth semantic (the token is fine; the user is not authorized) and it is the mechanism behind the map's note that non-admins should be rejected at the consent screen instead.

---

## 4. `config/mcp.php` and `authorization_server`

### Publishing is optional

`McpServiceProvider::register()` calls `$this->mergeConfigFrom(__DIR__.'/../../config/mcp.php', 'mcp')` (line 29), so every key resolves with the package defaults whether or not the file is published. `config/mcp.php` does **not** exist in this repo today (confirmed — `ls config/`). Publishing is `php artisan vendor:publish --tag=mcp-config` (`McpServiceProvider::registerPublishing()`, lines 66-68).

**But the default is permissive.** `vendor/laravel/mcp/config/mcp.php:18-22` ships `'redirect_domains' => ['*']`, and `OAuthRegisterController` short-circuits all redirect validation when `*` is present (lines 38-40). Combined with the unauthenticated `POST /oauth/register` endpoint, that means anyone can register a client with any redirect URI. **Recommendation: publish the config and narrow `redirect_domains`** to the schemes and hosts the intended clients actually use, plus `custom_schemes` for native clients (`claude`, `cursor`, `vscode` are the commented-out examples). Note `isValidRedirectUri()` only consults `custom_schemes` for non-`http(s)` schemes, and localhost is allowed only when a localhost entry is in `redirect_domains` (lines 42-44, 156-166).

### `authorization_server` behind Render's TLS termination

Both metadata builders fall back to `url('/')`:

```php
'issuer' => config('mcp.authorization_server') ?? url('/'),                    // Registrar.php:157
'authorization_servers' => [config('mcp.authorization_server') ?? url('/')],   // Registrar.php:175
```

`url('/')` inside an HTTP request derives its root from the current `Request` (scheme + host), not from `APP_URL`. So the question is whether the request looks like HTTPS inside the container. It does:

- `app/Http/Middleware/TrustProxies.php` sets `$proxies = '*'` and `$headers` includes `Request::HEADER_X_FORWARDED_PROTO` (lines 20-30). Render's ingress sets that header.
- `TrustProxies` is the **first** global middleware (`app/Http/Kernel.php:39-47`), so the scheme is corrected before anything else runs.
- The app does not call `URL::forceScheme()` or `forceRootUrl()` anywhere (grepped `app/`, `config/`, `bootstrap/`).
- `render.yaml` sets `OCTANE_HTTPS=false`, so Octane is _not_ forcing the scheme — the whole thing rests on `TrustProxies`.

**Conclusion (confidence: high):** `authorization_server` is **not strictly required**; `url('/')` should already produce `https://davidharting.com/`. Supporting evidence: the site's existing absolute URLs (feeds, canonical links) are already generated the same way and are correct in production.

**Recommendation anyway:** set it explicitly. RFC 8414 issuer identifiers must match byte-for-byte between the metadata document and what the client derived, so a silent scheme or trailing-slash drift is a confusing failure mode, and the value is a one-line config. If it is set, it must also be right in PR preview environments, where `config/app.php:63` swaps `APP_URL` for `RENDER_EXTERNAL_URL` — so bind it to the same expression rather than hardcoding the production host. **Not verified end-to-end** (Passport is not installed, so the metadata endpoints could not be hit); this is reasoning from the code paths above.

Related and unverified: `url('/'.$path)` on the nested protected-resource route builds `resource` from the request path, and `AddWwwAuthenticateHeader` builds its `resource_metadata` link with `route('mcp.oauth.protected-resource.nested', ['path' => $request->path()])`. Both are request-derived, so they inherit whatever `TrustProxies` decided. Worth an actual `curl` against a preview environment during the install ticket.

---

## 5. Migrations and Postgres

`passport:install` / `vendor:publish --tag=passport-migrations` copies five files into `database/migrations/` (`PassportServiceProvider::registerPublishing()`; the provider does **not** call `loadMigrationsFrom`, so they only exist once published — they become repo-owned files, reviewed like any other migration here):

| File                                                      | Table                  |
| --------------------------------------------------------- | ---------------------- |
| `2016_06_01_000001_create_oauth_auth_codes_table.php`     | `oauth_auth_codes`     |
| `2016_06_01_000002_create_oauth_access_tokens_table.php`  | `oauth_access_tokens`  |
| `2016_06_01_000003_create_oauth_refresh_tokens_table.php` | `oauth_refresh_tokens` |
| `2016_06_01_000004_create_oauth_clients_table.php`        | `oauth_clients`        |
| `2024_06_01_000001_create_oauth_device_codes_table.php`   | `oauth_device_codes`   |

Column types used: `char(80)` primary keys, `foreignId`, `foreignUuid`, `uuid`, `nullableMorphs`, `text`, `boolean`, `dateTime`, `timestamps`.

**Postgres assessment (confidence: high for the schema, medium for operational surprises):**

- No `Schema::create` here declares an actual foreign-key constraint — `foreignId` / `foreignUuid` only create the column (plus `->index()` where written). So there is no ordering or cascade concern against `users`.
- `foreignUuid('client_id')` and `uuid('id')` map to Postgres `uuid`. Fine on PG 17 (`render.yaml`, `postgresMajorVersion: "17"`).
- `char(80)` maps to `bpchar(80)`, which is blank-padded. Passport writes exactly-80-character token identifiers, so no padding occurs in practice, and `bpchar` comparison ignores trailing whitespace anyway. **Low risk, worth a glance if token lookups ever behave oddly.**
- `nullableMorphs('owner')` creates `owner_type varchar(255)` + `owner_id bigint` + a composite index. Fine.
- These are all fresh `CREATE TABLE`s on new table names, so the repo's `database/views/` caveat (Postgres refusing `DROP COLUMN` / `ALTER COLUMN TYPE` on a column a view selects — see `CLAUDE.md`) **does not apply**. `media_tracking_summary` is untouched.
- The migrations respect `config('passport.connection')` via `getConnection()`; leaving `PASSPORT_CONNECTION` unset uses the default `pgsql` connection.

**Not verified:** these migrations were not run against a Postgres instance, because installing Passport was out of scope. The install ticket should run `php artisan migrate` on a fresh database (CI already does this) as the real check.

### The Render-specific trap: signing keys

`PassportServiceProvider::makeCryptKey()` reads `config("passport.{$type}_key")` and falls back to `file://storage/oauth-{private,public}.key`. `passport:keys` writes those files into `storage/`.

**Render containers are rebuilt from the `Dockerfile` on every deploy**, and the web service, worker, and cron jobs are separate containers. Filesystem keys would therefore differ per container and be regenerated on every deploy, invalidating every issued token. The fix is the documented env path: publish `config/passport.php` (`--tag=passport-config`) and set `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY`. This repo already has the right vehicle — secrets are mounted as a Render secret file and linked to `/app/.env` by `scripts/render-with-secrets.sh` (`render.yaml`, `RENDER_SECRETS_ENV_FILE`). Note `makeCryptKey()` does `str_replace('\\n', "\n", ...)`, so a literal-`\n` single-line value is acceptable.

**Confidence: high** that filesystem keys are wrong for this deployment; **not verified** that the secret-file path carries multi-line PEM values cleanly — that is a concrete thing for the install ticket to test in a preview environment.

---

## 6. `App\Models\User` — and the Sanctum trap

### What Passport 13 wants

Per the docs and `src/HasApiTokens.php` on `13.x`:

```php
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens;
}
```

Passport 13 **does** still use a trait called `HasApiTokens`, and it adds a companion interface, `Laravel\Passport\Contracts\OAuthenticatable`, which the trait is declared `@phpstan-require-implements` against. The interface requires `oauthApps()`, `tokens()`, `tokenCan()`, `tokenCant()`, `createToken()`, `currentAccessToken()`, `withAccessToken()`, `getProviderName()`.

The trait is not decorative. `TokenGuard::authenticateViaBearerToken()` ends with `return $user?->withAccessToken(AccessToken::fromPsrRequest($psr));` — **without the trait, every authenticated request fatals.**

### The conflict is real and fatal

`app/Models/User.php:12,16` currently imports and uses `Laravel\Sanctum\HasApiTokens`. The two traits collide on **six** method names:

| Method                 | Sanctum (`vendor/laravel/sanctum/src/HasApiTokens.php`) | Passport (`13.x/src/HasApiTokens.php`) |
| ---------------------- | ------------------------------------------------------- | -------------------------------------- |
| `tokens()`             | line 25                                                 | yes                                    |
| `tokenCan()`           | line 36                                                 | yes                                    |
| `tokenCant()`          | line 47                                                 | yes                                    |
| `createToken()`        | line 60                                                 | yes                                    |
| `currentAccessToken()` | line 94                                                 | yes                                    |
| `withAccessToken()`    | line 105                                                | yes                                    |

PHP does not merge colliding trait methods; it fatals at class-compile time. Verified by running a stub reproducing both traits' exact method sets:

```
Fatal error: Trait method Sanctum\HasApiTokens::tokens has not been applied as
App\User::tokens, because of collision with Passport\HasApiTokens::tokens
```

There is a second, quieter problem: both traits are literally named `HasApiTokens`, so a single `use Laravel\...\HasApiTokens;` import statement cannot even name both without aliasing.

**Resolution — replace, do not add.** Swap the import in `app/Models/User.php` from `Laravel\Sanctum\HasApiTokens` to `Laravel\Passport\HasApiTokens` and add `implements OAuthenticatable`. This is safe here:

- Nothing in `app/`, `routes/`, `database/` or `tests/` calls `createToken()`, `tokenCan()`, or references `personal_access_tokens` (grepped — the only hit is the migration that creates the table).
- `config/auth.php` defines no `sanctum` guard.
- `Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful` is commented out in `app/Http/Kernel.php:65`.

Sanctum is unused scaffolding in this app. The `personal_access_tokens` table (`database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php`) and `config/sanctum.php` can stay or be cleaned up separately; that is a decision for the install ticket, not a blocker. **Do not** try to keep both traits with `insteadof` / `as` aliasing — it would satisfy the compiler but leave two token systems half-wired on one model.

`Passport::tokensCan()` and the trait's `getProviderName()` both read `config('auth.guards')` looking for a guard with `driver === 'passport'` and throw `LogicException` if the model does not match any such guard's provider — so the `config/auth.php` change in §2 is a hard prerequisite for the model change, not an independent step.

---

## 7. `AddWwwAuthenticateHeader` is automatic

`Registrar::web()` hardcodes it (`vendor/laravel/mcp/src/Server/Registrar.php:47-57`):

```php
$route = Router::post($route, ...)->middleware([
    ReorderJsonAccept::class,
    AddWwwAuthenticateHeader::class,
]);
```

**Do not add it manually.** Anything passed to `->middleware(...)` is _appended_ to that array (`Illuminate\Routing\Route::middleware()`, lines 1084-1103, `array_merge`), and while `Router::uniqueMiddleware` at the end of the sort would dedupe an exact duplicate, adding it explicitly only creates the illusion that ordering is under the route file's control. It is not — the priority sorter owns that (§3).

Its behaviour (`src/Server/Middleware/AddWwwAuthenticateHeader.php`):

- Passes through anything that is not a **401** (lines 21-23). 403s from `can:administrate` get no header.
- If the route `mcp.oauth.protected-resource.nested` exists — i.e. `Mcp::oauthRoutes()` has been called — it emits `Bearer realm="mcp", resource_metadata="<nested discovery URL for this request's path>"` (lines 25-33).
- Otherwise it emits the Sanctum-flavoured fallback `Bearer realm="mcp", error="invalid_token"` (lines 36-41).

So **calling `Mcp::oauthRoutes()` is what upgrades the challenge from a dead end into a real discovery pointer.** Both servers share it: the header is emitted per-route via `$request->path()`, so `/mcp/admin` gets `/.well-known/oauth-protected-resource/mcp/admin`. The existing public `/mcp` route never 401s, so nothing changes there.

Also automatic: `McpServiceProvider::registerMcpScope()` registers an `$this->app->booted(...)` callback that runs `Registrar::ensureMcpScope()` (lines 127-132). `ensureMcpScope()` no-ops if `Laravel\Passport\Passport` does not exist, otherwise it reads `Passport::$scopes`, adds `'mcp:use' => 'Use MCP server'` if missing, and calls `Passport::tokensCan($current)` — which **replaces** the whole array (`Passport.php:285-288`). Because the callback fires on `booted` (after every provider's `boot()`), any `Passport::tokensCan([...])` this app declares in `AppServiceProvider::boot()` is read first and preserved, with `mcp:use` merged on top. **Ordering is safe; confidence: high** (read both call sites).

---

## 8. Loose ends the install ticket should pick up

1. **The consent screen is not wired for you.** `laravel/mcp` ships `resources/views/mcp/authorize.blade.php` and publishes it under tag `mcp-views` to `resources/views/mcp/authorize.blade.php` — note, _not_ `views/vendor/passport/`. Nothing in the package calls `Passport::authorizationView()` (grepped all of `vendor/laravel/mcp/src`; the only `Passport::` references in the whole package are the three lines in `Registrar::ensureMcpScope()`). To use that view the app must call `Passport::authorizationView('mcp.authorize')` itself. This is also the natural hook for the map's "reject non-admins at the consent screen" requirement: `AuthorizationController::authorize()` calls `$viewResponse->withParameters([... 'user' => $user ...])`, so a closure passed to `authorizationView()` can inspect the user and refuse.
2. **Scope enforcement** — `auth:api` does not check `mcp:use`; see §2.
3. **`redirect_domains` defaults to `*`** with an unauthenticated registration endpoint; see §4.
4. **Device-code, client-credentials, and JSON API routes** are on by default; see §1.
5. **Key material on Render** is the highest-risk unverified item; see §5.

## Explicitly not determined

- Whether the published metadata endpoints actually return `https://` URLs in a Render preview environment. Reasoned from `TrustProxies` + `url()`, not observed. **Requires Passport installed and a live preview.**
- Whether multi-line PEM keys survive the `RENDER_SECRETS_ENV_FILE` → `/app/.env` path used by `scripts/render-with-secrets.sh`.
- The exact resolved version of `league/oauth2-server` this app would get (Passport requires `^9.2`); no dependency resolution was run, since `composer.json` was deliberately left alone.
- Whether any Filament or Breeze route conflicts with the `oauth` prefix. `php artisan route:list` was not run — `vendor/` is not installed in this research worktree, and Passport is not installed anywhere.
