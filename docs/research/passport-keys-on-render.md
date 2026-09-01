# Provisioning Laravel Passport signing keys on Render

Research for [#184](https://github.com/davidharting/davidharting.com/issues/184) (child of [#180](https://github.com/davidharting/davidharting.com/issues/180)).

**Question:** how should Passport's `oauth-private.key` / `oauth-public.key` be provisioned on Render, for production and for PR preview environments?

Every claim below is labelled with a confidence level and the primary source it came from. Where Render's documentation is silent, that is called out explicitly rather than papered over.

---

## Recommendation (short version)

1. **Do not use key files. Use `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY`.** Passport 13 reads them from config, they take precedence over files, and the in-memory path skips league's file-permission check entirely — which is the failure mode that bites containerised deploys.
2. **Put them in the Render secret files this repo already mounts** — `secrets.env` for production, `staging.secrets.env` for previews. `scripts/render-with-secrets.sh` symlinks whichever one `RENDER_SECRETS_ENV_FILE` names to `/app/.env`, so the value is parsed by phpdotenv and reaches `env()` with no new mechanism, no new Blueprint fields, and no dashboard-only service settings.
3. **Use the single-line escaped form** — `PASSPORT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIIJ…\n-----END PRIVATE KEY-----"`. It survives both the phpdotenv path and a raw-env-var path, and it is line-oriented so nothing in the toolchain can mangle it.
4. **Separate keypairs for production and previews.** One keypair for prod (in `secrets.env`); one keypair shared by all previews (in `staging.secrets.env`).
5. **Never generate keys at deploy time.** They would rotate on every release and invalidate every issued access token.

---

## 1. Does Passport 13 read keys from environment variables?

**Yes. Confidence: high. Source: Passport source, `v13.7.6`.**

`Laravel\Passport\PassportServiceProvider::makeCryptKey()` is the single place both the authorization server and the resource server get their key from:

```php
// src/PassportServiceProvider.php (v13.7.6)
protected function makeCryptKey(string $type): CryptKey
{
    $key = str_replace('\\n', "\n", config("passport.{$type}_key") ?? '');

    if (! $key) {
        $key = 'file://'.Passport::keyPath('oauth-'.$type.'.key');
    }

    return new CryptKey($key, null, Passport::$validateKeyPermissions);
}
```

<https://github.com/laravel/passport/blob/v13.7.6/src/PassportServiceProvider.php>

and the package's own config binds those to env vars:

```php
// config/passport.php (v13.7.6)
'private_key' => env('PASSPORT_PRIVATE_KEY'),
'public_key'  => env('PASSPORT_PUBLIC_KEY'),
```

<https://github.com/laravel/passport/blob/v13.7.6/config/passport.php>

Three consequences worth stating precisely:

- **Env vars win over files.** The file path is only consulted when the config value is empty. There is no merge and no fallback in the other direction.
- **You do not need to publish the config file.** The Laravel Passport docs say to run `vendor:publish --tag=passport-config` first, but `PassportServiceProvider::register()` calls `$this->mergeConfigFrom(__DIR__.'/../config/passport.php', 'passport')` (line 108 of the same file), so `config('passport.private_key')` resolves from the package default. Publishing is optional. **Confidence: high** (source read directly).
- **`str_replace('\n', "\n", …)` is undocumented.** It appears nowhere in the Laravel docs — only in the source. It is what makes the single-line escaped form work when the value arrives as a real OS environment variable (which is _not_ dotenv-parsed).

Official docs for the feature: <https://laravel.com/docs/12.x/passport#deploying-passport> → "Loading Keys From the Environment". The docs' example uses a _real_ multi-line value:

> ```ini
> PASSPORT_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----
> <private key here>
> -----END RSA PRIVATE KEY-----"
> ```

---

## 2. What exact format do multi-line PEM keys need?

This is the classic breakage, and the answer differs depending on **which of two paths** the value travels. This repo has both available, so it matters.

### Path A — value lives in the secret file that becomes `/app/.env` (this repo's current mechanism)

`scripts/render-with-secrets.sh` does `ln -sf "$RENDER_SECRETS_ENV_FILE" /app/.env`, so Laravel's `LoadEnvironmentVariables` parses the secret file with **vlucas/phpdotenv**. Both forms work:

- **Real multi-line, double-quoted.** `Dotenv\Parser\Lines::looksLikeMultilineStart()` triggers on the literal two-character sequence `="` appearing in the line, and buffers lines until an unescaped closing `"`. So the value **must** be written `KEY="…` with _no space around the `=`_ — `KEY = "…` will not start a multi-line entry. **Confidence: high.** Source: <https://github.com/vlucas/phpdotenv/blob/master/src/Parser/Lines.php>
- **Single-line with `\n` escapes.** Inside a double-quoted value, phpdotenv's `EntryParser` treats `\` as an escape introducer and, for `f n r t v`, applies `stripcslashes()`. So `"…-----\nMIIJ…"` yields a **real newline** at parse time. **Confidence: high.** Source: `ESCAPE_SEQUENCE_STATE` in <https://github.com/vlucas/phpdotenv/blob/master/src/Parser/EntryParser.php>

    Note this means Passport's own `str_replace('\n', "\n", …)` is a no-op on this path — phpdotenv already did the work. That is harmless; there are no `\n` sequences left to convert, and a PEM's base64 body is `[A-Za-z0-9+/=]` only, so there is no way for the replace to corrupt real key material.

### Path B — value set as a genuine Render environment variable (dashboard, env group, or `render.yaml`)

No dotenv parsing happens. The value reaches PHP through `$_SERVER`/`$_ENV` exactly as stored. So:

- **A literal `\n` stays literal** — and _this_ is where Passport's `str_replace` earns its keep. The single-line escaped form works.
- **A real multi-line value also works**, because Render supports multi-line env var values. Render's own bulk-`.env` import documentation shows a PEM-shaped example:

    > ```bash
    > # Multi-line value
    > KEY_3="-----BEGIN-----
    > value of KEY_3
    > -----END-----"
    > ```

    <https://render.com/docs/configure-environment-variables> → "Adding in bulk from a .env file". **Confidence: high** (verbatim from Render docs). Note this documents the _import syntax_; the stored value ends up containing real newlines.

- **`render.yaml` cannot carry it anyway** — see §3. Do not put a private key in a Blueprint file.

### The failure mode, so it is recognisable

`League\OAuth2\Server\CryptKey::__construct()` does this:

```php
if (str_starts_with($keyPath, 'file://') === false && $this->isValidKey($keyPath, …)) {
    // treat the string as key contents
} elseif (is_file($keyPath)) {
    // treat it as a path
} else {
    throw new LogicException('Invalid key supplied');
}
```

<https://github.com/thephpleague/oauth2-server/blob/9.2.0/src/CryptKey.php> (Passport 13 requires `league/oauth2-server: ^9.2` — <https://github.com/laravel/passport/blob/v13.7.6/composer.json>)

`isValidKey()` calls `openssl_pkey_get_private()` / `openssl_pkey_get_public()` and requires the result to be RSA or EC. So a PEM with mangled newlines fails to parse, is not a file either, and you get a bare **`LogicException: Invalid key supplied`** — with no indication that newlines were the cause. **Confidence: high** (source read directly).

### Recommended form

Single-line, escaped, double-quoted. It works on both paths, it is one line so no editor or copy-paste can reflow it, and it is trivially diffable:

```dotenv
PASSPORT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIIJKQIBAAKCAg…\n-----END PRIVATE KEY-----\n"
PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\nMIICIjANBgkq…\n-----END PUBLIC KEY-----\n"
```

Generate and encode in one step:

```bash
openssl genrsa -out oauth-private.key 4096
openssl rsa -in oauth-private.key -pubout -out oauth-public.key

# emit the escaped single-line env lines
awk 'BEGIN{printf "PASSPORT_PRIVATE_KEY=\""} {printf "%s\\n", $0} END{print "\""}' oauth-private.key
awk 'BEGIN{printf "PASSPORT_PUBLIC_KEY=\""}  {printf "%s\\n", $0} END{print "\""}' oauth-public.key
```

`openssl genrsa` on OpenSSL 3.x emits PKCS#8 (`-----BEGIN PRIVATE KEY-----`) rather than the PKCS#1 header shown in the Laravel docs. Both parse fine — `openssl_pkey_get_private()` accepts either. `php artisan passport:keys` uses phpseclib's `RSA::createKey()` and emits PKCS#1 for the private key and PKCS#8 for the public key (<https://github.com/laravel/passport/blob/v13.7.6/src/Console/KeysCommand.php>). **Confidence: high** for "both parse"; the header difference is cosmetic.

---

## 3. What can Render express, and where?

| Mechanism                                             | Declarable in `render.yaml`?         | Multi-line safe?                                                 | Reaches PR previews?           |
| ----------------------------------------------------- | ------------------------------------ | ---------------------------------------------------------------- | ------------------------------ |
| Plain env var, `value:`                               | Yes                                  | YAML block scalar would work, but **never commit a private key** | Yes (copied)                   |
| Env var, `sync: false` (dashboard-prompted)           | Declared in YAML, value in dashboard | Yes                                                              | **No** — explicitly not copied |
| `previewValue:` override                              | Yes                                  | Same caveat                                                      | Preview-only override          |
| Blueprint-defined env group (`envVarGroups:`)         | Yes                                  | Same caveat                                                      | Copied per-PR (see §4)         |
| Dashboard-only env group, referenced via `fromGroup:` | Reference only                       | Yes                                                              | Yes, **shared** (see §4)       |
| Secret file                                           | **No**                               | Yes                                                              | See §4                         |

**Secret files cannot be declared in `render.yaml`. Confidence: high.** Two independent confirmations:

- The Blueprint YAML reference (<https://render.com/docs/blueprint-spec>) documents `envVars` (`value`, `generateValue`, `sync`, `fromDatabase`, `fromService`, `fromGroup`, `previewValue`) and `envVarGroups`, and has no secret-file concept anywhere.
- Render's published JSON schema, <https://render.com/schema/render.yaml.json>, contains no `secretFile` / `secretFiles` key at all (checked by string search against the fetched schema).

Secret files are managed in the dashboard, or via the REST API:

> `PUT /v1/env-groups/{envGroupId}/secret-files/{envVarKey}` with body `{"content": "<string>"}` — "Add or update a particular secret file in an particular environment group."
>
> <https://api-docs.render.com/reference/update-env-group-secret-file>

**Confidence: high.** So a secret file _is_ still IaC-able — just via a scripted API call rather than a Blueprint field. The Render Terraform provider also exposes `secret_files` on `render_env_group` (<https://registry.terraform.io/providers/render-oss/render/latest/docs/resources/env_group>). **Confidence: medium** — I read the registry docs summary, not the provider source.

Other facts from Render's docs, verbatim:

> "At runtime, the secret file is available at `/etc/secrets/<filename>`."
>
> "The combined size of all secret files uploaded to any given service or environment group cannot exceed 1 MB."
>
> "Environment groups are collections of environment variables and/or secret files that you can link to any number of different services."
>
> "If a service defines an environment variable in its individual settings, that value always takes precedence over any linked environment groups that also define the variable."

<https://render.com/docs/configure-environment-variables>

A 4096-bit RSA keypair in PEM is roughly 3.5 KB, so the 1 MB budget is a non-issue.

---

## 4. Do PR previews inherit env group secrets? Do they inherit secret files?

### What Render documents (verbatim)

> "Placeholder environment variables defined with `sync: false` are not copied to preview environments. To share secret variables across preview environments:
>
> 1. Manually create an environment group in the Dashboard.
> 2. Add one or more environment variables.
> 3. Reference the environment group in your `render.yaml` file, as needed.
>
> You can also use an environment group that's managed by a Blueprint, if it's not the same Blueprint that you're using to manage your preview environments. If you use the same Blueprint for both, a new environment group will be created for each preview environment. Placeholder environment variables will not be copied to these environment groups."

<https://render.com/docs/preview-environments> → "Placeholder environment variables"

> "Render does not include `sync: false` environment variables in preview environments."

<https://render.com/docs/blueprint-spec> → "Prompting for secret values"

### Where Render's docs are silent — say so

**Render's preview-environments page never mentions secret files.** The string "secret file" does not appear on it (verified by fetching and stripping the page). So the platform's own documentation does **not** answer "are secret files copied into a preview environment's env-group copy?" Anyone building on this should treat the answer below as empirically derived, not documented.

### What this repo already established empirically

This is the strongest evidence available, and it lives in our own history. Commit `28fccce` ("Revert age encryption; deliver staging secrets via shared dashboard env group", PR #154) records:

> "When Render creates a PR preview, it copies all env groups (including workspace-level ones) into per-PR copies. **Secret files are stripped from those copies.** We hit this first."
>
> "We tried defining `AGE_SECRET_KEY` as a `sync:false` env var … But `sync:false` env vars are also not copied to PR env group copies."
>
> "if an env group is defined IN the Blueprint, Render creates a per-PR copy of it. If it is only REFERENCED via `fromGroup` (definition lives solely in the dashboard), Render treats it as a shared external resource and all preview services use it directly — no copying, no stripping."

The now-deleted `docs/pr-preview-env-feedback.md` (recoverable at `git show 8898f29^:docs/pr-preview-env-feedback.md`) records the exact symptom: preview deploys failing with `Missing Render secrets file: /etc/secrets/staging.secrets.env`.

That is why `render.yaml` today references but does **not** define `davidhartingdotcom-preview-env-secrets`, and why `RENDER_SECRETS_ENV_FILE` has `previewValue: /etc/secrets/staging.secrets.env`. **This arrangement is currently in production and working**, which is itself the proof that a _dashboard-managed_ env group's secret file **is** mounted at `/etc/secrets/<filename>` inside preview services.

**Summary — confidence: high, from in-repo empirical evidence plus Render docs for the env-var half:**

|                        | Blueprint-defined env group            | Dashboard-only env group (`fromGroup` reference) |
| ---------------------- | -------------------------------------- | ------------------------------------------------ |
| Plain env vars         | Copied per-PR                          | Shared directly                                  |
| `sync: false` env vars | **Not** copied (documented)            | N/A — can't use `sync: false` in a group         |
| Secret files           | **Stripped** (empirical, undocumented) | **Available** (empirical, undocumented)          |

### How to re-verify empirically, cheaply

Do not take the above on faith when it matters. On any open PR's preview web service, open Render Shell and run:

```bash
ls -l /etc/secrets/
stat -c '%a %U %G %n' /etc/secrets/*
grep -c PASSPORT_PRIVATE_KEY /etc/secrets/staging.secrets.env
php artisan tinker --execute 'dd([
    "private_len" => strlen((string) config("passport.private_key")),
    "has_newlines" => str_contains((string) config("passport.private_key"), "\n"),
]);'
```

A non-zero `private_len` with `has_newlines => true` proves the whole chain (secret file → symlink → phpdotenv → config → Passport). A `LogicException: Invalid key supplied` on the first OAuth request proves the opposite.

---

## 5. Should production and previews share one keypair?

**Recommendation: separate keypairs. Confidence: high (judgement, grounded in the mechanics below).**

The arguments:

- **Cost of separation is zero.** The two secret files are already separate (`secrets.env` vs `staging.secrets.env`) and already carry different values for other things (R2 buckets, etc.). Adding a different `PASSPORT_PRIVATE_KEY` to each is one extra line per file. There is no shared-key convenience to give up.
- **Previews are the more exposed surface.** They are publicly reachable at a guessable URL (`https://davidhartingdotcom-web-pr-<N>.onrender.com`), they run `RUN_DEV_SEEDER=true` so they contain seeded accounts with known-shape credentials, and **one shared secret file backs every preview simultaneously** — so a leak from any single PR is a leak of the key used by all of them. Handing that surface the production signing key is a bad trade for no gain.
- **Cross-environment token replay is already blocked by the database, but do not rely on it alone.** Passport's `AccessTokenRepository::isAccessTokenRevoked()` is `Passport::token()->newQuery()->whereKey($tokenId)->where('revoked', false)->doesntExist()` — a token id absent from the table reads as _revoked_. Preview environments get their own fresh Postgres instance ("A preview environment creates new instances of the services and datastores defined in your Blueprint. These instances do not copy any data from existing services." — <https://render.com/docs/preview-environments>), so a preview-minted JWT presented to production would pass signature validation and then fail the DB lookup. That is a real second layer, but it is one query away from being the _only_ layer. Separate keys mean the signature check fails first. Source: <https://github.com/laravel/passport/blob/v13.7.6/src/Bridge/AccessTokenRepository.php>. **Confidence: high.**
- **Rotating the preview key becomes a non-event.** With shared keys, rotating after a suspected preview leak means invalidating every production token too.

Practical note: because all previews share `staging.secrets.env`, they share one keypair _with each other_. That is fine — each preview has its own database, so tokens still do not cross between PRs.

---

## 6. Where does Passport look for key files by default, and can that be changed?

**Default: `storage_path('oauth-private.key')` and `storage_path('oauth-public.key')` — i.e. `/app/storage/` in this image. Confidence: high.**

```php
// src/Passport.php (v13.7.6)
public static function loadKeysFrom(string $path): void { static::$keyPath = $path; }

public static function keyPath(string $file): string
{
    $file = ltrim($file, '/\\');

    return isset(static::$keyPath)
        ? rtrim(static::$keyPath, '/\\').DIRECTORY_SEPARATOR.$file
        : storage_path($file);
}
```

<https://github.com/laravel/passport/blob/v13.7.6/src/Passport.php>

So `Passport::loadKeysFrom('/etc/secrets')` in `AppServiceProvider::boot()` would make Passport read `/etc/secrets/oauth-private.key` and `/etc/secrets/oauth-public.key` — two Render secret files, prod and preview groups each holding their own contents at the same filenames. Documented at <https://laravel.com/docs/12.x/passport#deploying-passport>.

**This is a viable alternative, and I am recommending against it, for one concrete reason:** the file path re-enables league's permission check.

```php
// CryptKey::__construct(), when the key came from a file
if ($keyPermissionsCheck === true && PHP_OS_FAMILY !== 'Windows') {
    $keyPathPerms = decoct(fileperms($this->keyPath) & 0777);
    if (in_array($keyPathPerms, ['400', '440', '600', '640', '660'], true) === false) {
        trigger_error('Key file … permissions are not correct …', E_USER_NOTICE);
    }
}
```

`Illuminate\Foundation\Bootstrap\HandleExceptions::bootstrap()` calls `error_reporting(-1)`, and `handleError()` converts any non-deprecation error in the reporting mask into a thrown `ErrorException`. `E_USER_NOTICE` is not a deprecation level. **So on Laravel, that "notice" is a 500, not a log line.** Source: <https://github.com/laravel/framework/blob/12.x/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php>. **Confidence: high.**

Render's documented posture on secret-file permissions is only that group `1000` must be able to read them:

> "When accessing secret files in Docker services, you might encounter permission errors … To resolve this, make sure your application user is in group `1000`."
>
> <https://render.com/docs/docker-secrets>

Render does not document the actual mode. Our `Dockerfile` has no `USER` instruction so the process runs as root and _reading_ is not a problem — but whether the mode happens to be one of `400/440/600/640/660` is **unverified**. If it is, say, `0644`, Passport blows up on the first OAuth request. The escape hatch exists — `Passport::$validateKeyPermissions = false` (a plain public static in v13; the older `Passport::ignoreKeyPermissionsValidation()` helper is gone) — but this is a whole category of risk that the env-var path does not have, because `CryptKey` sets `$keyPermissionsCheck = false` for in-memory key contents ("There's no file, so no need for permission check.").

If someone does want the file route, verify first with `stat -c '%a' /etc/secrets/oauth-private.key` in Render Shell.

---

## 7. Octane interactions

**Keys are re-read per request, not once per worker. Confidence: high.**

`Laravel\Octane\Worker::handle()` does `CurrentApplication::set($sandbox = clone $this->app)` per request and `$sandbox->flush()` in `finally`. Singletons resolved _during_ a request live in the sandbox clone and are discarded with it; only things resolved during worker boot persist in the base container. Source: <https://github.com/laravel/octane/blob/2.x/src/Worker.php>

`AuthorizationServer` and `ResourceServer` are registered as `$this->app->singleton(…)` in `PassportServiceProvider::register()`, and `makeCryptKey()` runs inside those closures. Neither is in `Octane::defaultServicesToWarm()`, and `config/octane.php` in this repo leaves `warm` at the default and `flush` empty. So:

- With **file-based** keys: `file_get_contents()` + `openssl_pkey_get_*()` on every request that touches an OAuth endpoint or authenticates a bearer token.
- With **env-based** keys: no filesystem I/O at all; just a `str_replace` and an `openssl_pkey_get_*()` parse.

Neither is a meaningful cost — RSA _parsing_ is microseconds; RSA _signing_ (~milliseconds for 4096-bit) only happens when a token is actually minted. But the env path is strictly less work and has no per-request syscall. If token issuance ever shows up in profiles, `passport:keys --length=2048` is the lever, not caching.

Two more Octane notes:

- **Statics persist for the worker's lifetime.** `Passport::$keyPath` and `Passport::$validateKeyPermissions` are plain statics set in a provider's `boot()`, which runs once per worker. That is fine, but it means a key rotation requires a **redeploy/worker restart**, never just a config change. A Render env-group edit already triggers a deploy, so this is automatic here.
- **Config is not cached in this image.** `Dockerfile` ends its build steps with `php artisan optimize:clear`, so `env()` inside `config/passport.php` is evaluated at runtime, after `render-with-secrets.sh` has linked the secret file. **If anyone ever adds `config:cache`, it must run inside `render-with-secrets.sh` (i.e. in `preDeployCommand`), never at image build time** — otherwise `PASSPORT_PRIVATE_KEY` gets baked in as `null` and Passport silently falls back to the missing key file.

---

## 8. Why not generate keys at deploy time?

Because the private key is what signs access tokens, and the public key is what validates them. Regenerating on each deploy changes the signature every release, so **every outstanding access token stops validating**.

Nuance worth knowing: refresh tokens and authorization codes are _not_ signed with the RSA key — they are encrypted with `Passport::tokenEncryptionKey($encrypter)`, which defaults to `$encrypter->getKey()`, i.e. `APP_KEY` (`PassportServiceProvider::makeAuthorizationServer()`, and `Passport::tokenEncryptionKey()`). So a key rotation invalidates access tokens but leaves refresh tokens decryptable — a client could refresh its way back to a working access token. **Confidence: high** (source read directly), but this is not a reason to rotate casually; it just bounds the damage.

For preview environments the argument is weaker (the whole environment is disposable, and its database is fresh), but generating there anyway would mean previews behave differently from production in exactly the subsystem under test. Not worth it, and the shared `staging.secrets.env` makes the static-key path free.

---

## 9. Concrete plan

1. Generate two keypairs locally — one prod, one preview. Do not commit them.
2. Encode each as the escaped single-line form (§2).
3. Add to the **production** secret file `secrets.env` on the Blueprint-managed `davidhartingdotcom-shared` group's service, and to the **preview** secret file `staging.secrets.env` on the dashboard-managed `davidhartingdotcom-preview-env-secrets` group:
    ```dotenv
    PASSPORT_PRIVATE_KEY="…"
    PASSPORT_PUBLIC_KEY="…"
    ```
4. Change nothing in `render.yaml`. No new env vars, no new groups, no `sync: false`.
5. Do **not** run `php artisan passport:keys` anywhere in the Docker build, `preDeployCommand`, or `initialDeployHook`.
6. Verify on a preview with the tinker snippet in §4 before wiring up any OAuth client.
7. Store both keypairs in 1Password so rotation is possible without regenerating from nothing.

## 10. Adjacent, out of scope for #184

Preview hostnames vary per PR (`https://davidhartingdotcom-web-pr-<N>.onrender.com`), and `config/app.php` already handles this with `(env('IS_PULL_REQUEST') ? env('RENDER_EXTERNAL_URL') : null) ?? env('APP_URL', …)`. OAuth **client redirect URIs** are per-environment data, not keys, and will need the same treatment — most likely by having the dev seeder (already gated on `RUN_DEV_SEEDER=true` via `scripts/seed.sh` and `initialDeployHook`) create the preview's OAuth client with a redirect URI derived from `config('app.url')`. That belongs in its own ticket.

---

## Open unknowns

1. **Actual file mode of Render secret files at `/etc/secrets/`.** Undocumented. Only matters if we take the file-based route (§6), which this document recommends against. Verify with `stat -c '%a'` in Render Shell if that route is ever revisited.
2. **Render's behaviour for secret files in preview environments is undocumented.** The conclusion in §4 rests on this repo's own production experience (PR #154), not on Render's docs. It is currently true; it is not contractually guaranteed. Re-verify after any Render platform change that touches preview environments.
3. **Terraform provider `secret_files` support** was read from the registry documentation summary, not the provider source. Low stakes — we are not using Terraform.

## Sources

- Laravel Passport docs, "Deploying Passport" / "Loading Keys From the Environment": <https://laravel.com/docs/12.x/passport#deploying-passport>
- Passport `v13.7.6` source: [`PassportServiceProvider.php`](https://github.com/laravel/passport/blob/v13.7.6/src/PassportServiceProvider.php), [`Passport.php`](https://github.com/laravel/passport/blob/v13.7.6/src/Passport.php), [`config/passport.php`](https://github.com/laravel/passport/blob/v13.7.6/config/passport.php), [`Console/KeysCommand.php`](https://github.com/laravel/passport/blob/v13.7.6/src/Console/KeysCommand.php), [`Bridge/AccessTokenRepository.php`](https://github.com/laravel/passport/blob/v13.7.6/src/Bridge/AccessTokenRepository.php), [`composer.json`](https://github.com/laravel/passport/blob/v13.7.6/composer.json)
- `league/oauth2-server` 9.2.0 `CryptKey`: <https://github.com/thephpleague/oauth2-server/blob/9.2.0/src/CryptKey.php>
- `vlucas/phpdotenv`: [`Parser/Lines.php`](https://github.com/vlucas/phpdotenv/blob/master/src/Parser/Lines.php), [`Parser/EntryParser.php`](https://github.com/vlucas/phpdotenv/blob/master/src/Parser/EntryParser.php)
- Laravel framework 12.x `HandleExceptions`: <https://github.com/laravel/framework/blob/12.x/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php>
- Laravel Octane 2.x `Worker`: <https://github.com/laravel/octane/blob/2.x/src/Worker.php>
- Render, "Environment Variables and Secrets": <https://render.com/docs/configure-environment-variables>
- Render, "Preview Environments": <https://render.com/docs/preview-environments>
- Render, "Blueprint YAML Reference": <https://render.com/docs/blueprint-spec>
- Render, `render.yaml` JSON schema: <https://render.com/schema/render.yaml.json>
- Render, "Using Secrets with Docker": <https://render.com/docs/docker-secrets>
- Render API, "Add or update secret file": <https://api-docs.render.com/reference/update-env-group-secret-file>
- Render Terraform provider, `render_env_group`: <https://registry.terraform.io/providers/render-oss/render/latest/docs/resources/env_group>
- This repo: `render.yaml`, `scripts/render-with-secrets.sh`, `Dockerfile`, `config/app.php`, `config/octane.php`, commit `28fccce` (PR #154), deleted `docs/pr-preview-env-feedback.md` (at `8898f29^`)
