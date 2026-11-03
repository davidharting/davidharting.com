# Dependency updates

The goal is to stay fully current, majors included, without being among the
first to install a fresh release. Being current gets us security fixes; waiting
a week keeps us clear of most supply-chain attacks, which tend to be caught and
yanked within days.

## How updates arrive

`.github/dependabot.yml` runs every Monday morning for Composer, npm, GitHub
Actions and the Dockerfile's base image.

- **Cooldown.** A release must be 7 days old before Dependabot proposes it,
  14 days for a major. Security updates skip the cooldown.
- **Minor and patch** updates come as one grouped PR per ecosystem.
- **Majors** come as one PR per package.

Dependabot does not update everything. These are checked by hand during the
weekly pass below:

- The PHP version inside the FrankenPHP image tag in `Dockerfile`, and the
  matching `php-version` in CI and `scripts/sandbox/setup.sh`.
- `node` in `mise.toml` and `node-version` in the GitHub workflows.
- The `postgres` image in CI and the Postgres version in `render.yaml`.

## Weekly pass

A weekly agent routine works through the open dependency PRs. Merging is only
allowed when David has authorized it for the session doing the work, such as
in the routine's own instructions. Without that, get each PR verified as
described here and leave it for him. The "Ask David first" list always waits
for him.

Work one PR at a time, oldest first, and rebase the next one after each merge.

### Minor and patch groups

1. Wait for CI to pass. If it fails, fix the cause on the PR branch (a Pint
   rule change, a deprecation, a snapshot) and keep the fix small.
2. Read the changelogs for anything flagged as breaking or security-relevant.
3. Merge (squash), then confirm the deploy (see below).

### Majors

1. Read the upgrade guide. Make the code changes on the Dependabot branch, or
   on a new branch if the change is large, and run `mise run format`.
2. Run the whole suite: `vendor/bin/pest --compact --parallel`.
3. If the package touches what users see or how the app runs (Vite, Tailwind,
   DaisyUI, Livewire, Filament, Octane, the framework itself), add the
   `preview` label and check the preview at
   `https://davidhartingdotcom-web-pr-<PR>.onrender.com`: home page, a note,
   the media library, login, and the admin panel.
4. Merge once it is verified, then confirm the deploy.

### Ask David first

Do not merge these yourself. Get the PR green, then ask:

- Changes to authentication, OAuth/Passport, sessions or cookies.
- Changes to backups, mail, queues, storage (R2/S3) or the Telegram webhook,
  which a preview cannot fully exercise.
- A PHP minor version bump, a Postgres major, or a FrankenPHP major.
- Anything that needs a data migration or a change in Render or Cloudflare.
- Any PR where you could not verify the behavior that changed.

### After a merge

About five minutes after merging, check `list_deploys` for the prod web
service (`srv-d7lp26m7r5hc73b1h2tg`) and worker (`srv-d7lp26m7r5hc73b1h2sg`)
in workspace `tea-d6bths7tn9qs73c63ht0`. Each should have a `new_commit`
deploy of the merge commit that went live. Trigger a deploy only if it didn't
appear. Then load https://davidharting.com and confirm it responds.

## Installing by hand

When you add or update a package outside Dependabot, keep the same cooldown:

```bash
npm install <pkg> --min-release-age=7
```

Composer has no equivalent setting. Check the release date on Packagist before
requiring a version that is less than a week old. If a security fix is newer
than a week, take it anyway and say so in the PR.
