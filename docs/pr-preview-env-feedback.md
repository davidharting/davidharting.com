# Render PR Preview Environment — Setup Feedback

Notes from setting up PR preview environments on Render for the first time, shared as feedback for the team.

---

## Surprises and Friction Points

### 1. `RENDER_EXTERNAL_URL` is injected into production too

I assumed `RENDER_EXTERNAL_URL` was preview-specific, so I used it unconditionally in my pre-deploy script to register a Telegram webhook. This would have broken production by pointing the webhook at the `.onrender.com` URL instead of my custom domain. The actual signal to check is `IS_PULL_REQUEST`. The docs could be clearer that `RENDER_EXTERNAL_URL` is set in all web services, not just PR previews.

### 2. `initialDeployHook` cannot be nested inside `previews:`

I wanted to seed the database only on the first deploy of a preview environment, so I tried placing `initialDeployHook` inside the `previews:` block. The `render blueprint validate` tool rejected it:

```
field initialDeployHook not found in type file.ServicePreviews
```

`initialDeployHook` must go at the top level of the service definition. This means it also runs on first deploy of production, which isn't always desirable. It would be useful if `initialDeployHook` were a valid field inside `previews:`.

### 3. Running a seeder in `preDeployCommand` breaks on second push

My first attempt seeded the database in `preDeployCommand`. On the second push to the same PR, the seed command failed with unique constraint violations because the database persists across deploys to the same PR. The fix was `initialDeployHook`, which only runs once per preview environment lifetime.

### 4. Secret files are not copied when env groups are duplicated for PR previews — and neither are `sync: false` env vars

When a PR preview environment is created, Render copies the project env group into a new PR-specific copy, but **secret files are not included in that copy**. This wasn't documented clearly and only became apparent when the first preview deploy failed with:

```
Missing Render secrets file: /etc/secrets/staging.secrets.env
```

After discovering secret files weren't copied, I tried using a workspace-level env group with a `sync: false` plain env var (`AGE_SECRET_KEY`) to hold an encryption key, and committing an `age`-encrypted copy of the staging secrets to the repo. This seemed promising but hit another wall: **`sync: false` env vars are also not copied to PR preview env group copies**. Render intentionally omits dashboard-managed placeholder variables from preview copies.

The actual solution, discovered via community threads rather than official docs: **create the env group manually in the dashboard rather than defining it in the Blueprint**. Because the group isn't defined in the Blueprint, Render has no reason to create a per-PR copy of it — all preview services reference the same shared group directly, and the secret file is available. The `fromGroup` reference in `render.yaml` still works; the key is that the group definition itself lives only in the dashboard.

```yaml
# render.yaml — reference only, no definition
envVars:
    - fromGroup: my-staging-secrets # created manually in the dashboard
```

### 5. Project-scoped env groups must belong to an environment

Env var groups inside a project must live in a named environment (e.g., `prod`). There's no way to create a project-scoped group that sits outside of a specific environment for preview-only use. The manually-managed dashboard group (described above) sidesteps this because it lives at the workspace level rather than inside a project environment.

### 6. `renderSubdomainPolicy: disabled` is inherited by preview services

My production web service had `renderSubdomainPolicy: disabled` to avoid the onrender.com subdomain showing up in Cloudflare. Preview services inherited that setting, which caused the initial preview URL to return 404. The fix was to remove the policy restriction, but the fix didn't take effect on the existing preview environment.

Two downstream consequences worth noting:

- **Production is now exposed via the onrender.com subdomain.** Removing `renderSubdomainPolicy: disabled` means the production service is reachable at both the custom domain and `*.onrender.com`, bypassing any Cloudflare protections (WAF, caching, rate limiting) on that path.
- **The desired behavior must be enforced outside of Render.** Because the `previews:` block does not support `renderSubdomainPolicy` as an override, there is no way to disable the subdomain for production while keeping it enabled for previews within `render.yaml` alone. The workaround is to enforce the restriction at the CDN layer — for example, a Cloudflare WAF rule blocking requests where `Host == <service>.onrender.com` for the production service specifically.

### 7. Blueprint infrastructure changes don't apply via code deploy

When I updated `renderSubdomainPolicy` in `render.yaml`, that change wasn't picked up by the already-running preview environment. Infrastructure-level Blueprint changes only take effect when the Blueprint is synced — or, for preview environments specifically, when the preview is recreated (close and reopen the PR). This is easy to miss when iterating on Blueprint configuration.

### 8. The `render` CLI doesn't list preview service instances

`render services` only lists production services. There's no CLI command to inspect preview service IDs, logs, or status. Diagnosing preview deploy failures required jumping to the dashboard or writing raw API calls. CLI visibility into preview environments would significantly improve the debugging loop.

---

## Things That Worked Well

- `render blueprint validate` was invaluable — caught schema errors before deploy
- `previews: generation: off` on cron jobs worked exactly as expected
- `previewValue` overrides in env var groups are elegant — it's a clean way to point staging at a different R2 bucket or Telegram bot without duplicating env var definitions
- `IS_PULL_REQUEST` being available at runtime makes conditional logic (e.g., webhook registration) straightforward
