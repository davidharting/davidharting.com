# Development Environment Setup

Bootstrap a fresh development environment to run tests. This guide is designed for automated Claude Code and git worktree workflows.

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 24 or higher, and npm
- PostgreSQL 15 or higher
- libvips (`vipsthumbnail`, `vipsheader`) with HEIC support, and exiftool

## System tools for photos

Memory photos are processed by the libvips command line tools, and the photo tests read their output back with exiftool. Without them, photo tests fail and photos end up failed.

- **macOS:** `mise run setup` runs `brew bundle --no-upgrade`, which installs both from the `Brewfile`.
- **Ubuntu (CI, Claude Code cloud sandboxes):** `apt-get install libvips-tools libheif-plugin-libde265 libimage-exiftool-perl`. `scripts/sandbox/setup.sh` does this for you.
- **Debian 12 (Amp orbs, the production image):** `apt-get install libvips-tools libimage-exiftool-perl`. Its libheif decodes HEIC without a separate plugin. Production installs only `libvips-tools`.

## Setup Steps

### 1. Install Dependencies

```bash
composer install
npm install
```

In a Claude Code cloud sandbox, the environment's setup script runs `bash /home/user/davidharting.com/scripts/sandbox/setup.sh` instead. It installs PHP 8.4, Node 24, libvips and exiftool, unpacks a prebuilt `vendor/` from the newest `dev-sandbox-vendor` Actions artifact on main, runs both installs, then does steps 2 and 3 below. The sandbox's GitHub proxy blocks Composer's zip downloads for other repositories, so without the bundle Composer clones every package and takes minutes.

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
mkdir -p secrets/oauth && php artisan passport:keys
```

`passport:keys` writes the keypair Passport signs OAuth access tokens with. Without it, anything touching the `api` guard — including the test suite — fails with `Invalid key supplied`.

The keys land in `secrets/oauth/`, not Passport's default of `storage/`, because `AppServiceProvider` pins the path with `Passport::loadKeysFrom()`. `storage/` is runtime state the app writes to; signing keys are configuration. All of `secrets/` is gitignored, so every environment generates its own and none are ever committed.

### 3. PostgreSQL Setup

Pick the path for your environment. **Remote / CI / sandboxed Linux containers** (including cloud coding agents) use the Linux section and drive PostgreSQL directly. **Local macOS** development uses the pitchfork-managed workflow. mise + pitchfork are a macOS-local convenience, not a requirement anywhere else.

#### Linux / sandboxed environments

`scripts/sandbox/setup.sh` does this whole section, and step 2 as well, so in a Claude Code cloud sandbox it is already done. Re-running it is safe. Elsewhere on Linux, run it as root, or follow what it does:

- Start PostgreSQL and trust TCP connections from localhost. phpunit.xml connects as `david` with an empty password, and Postgres can't check an empty password.
- Create `root` with password `password` and `CREATEDB`, matching `.env`. It owns the `laravel` dev database.
- Create `david` as a `SUPERUSER`, matching `phpunit.xml`. It owns `laravel_test`, and `--parallel` needs it to create the `laravel_test_test_N` databases.

#### macOS (local dev via pitchfork)

On macOS, PostgreSQL is managed by [pitchfork](https://pitchfork.jdx.dev) as the `postgres` daemon in `pitchfork.toml` — you do **not** start it by hand. On first run the daemon initializes the mise-installed PostgreSQL 17 data directory, and its readiness script (`scripts/dev/db-bootstrap.sh`) idempotently creates the roles and databases below and runs migrations. `postgres = "17"` must be in your mise config (e.g. `~/repos/.config/mise/config.toml`).

Install tools and dependencies (one-off):

```bash
mise run setup
```

Bring up the stack — starts postgres, creates roles/databases, migrates, then starts octane/queue/vite:

```bash
mise run dev
```

The roles and databases created match `.env` and `phpunit.xml`: `laravel` for dev, and `laravel_test` owned by `david` — the `DB_USERNAME` hardcoded in `phpunit.xml` for the test database. Substitute your own username if different (see `scripts/dev/db-bootstrap.sh`).

If you only need the database (e.g. to run tests), `pitchfork start postgres` is enough. Stop everything with `mise run down`.

#### Both environments

`laravel` is for dev; `laravel_test` is where `php artisan test` runs (configured in `phpunit.xml`) so tests never clobber dev data.

Run migrations on the dev database:

```bash
php artisan migrate
```

### 4. Create Storage Symlink

```bash
php artisan storage:link
```

This creates a symlink from `public/storage` to `storage/app/public` so the local-public disk is accessible via the web server.

### 5. Build Frontend Assets

```bash
npm run build
```

### 6. Run Tests

```bash
vendor/bin/pest --compact --parallel
```

All tests should pass. Outside CI this records the Test Impact Analysis map on the first run, and later runs only re-run what your changes affect. See the next section.

## Test Impact Analysis (PCOV)

`tests/Pest.php` turns on Pest's Test Impact Analysis (TIA) for every run outside CI. TIA needs a coverage driver, and this project uses PCOV, which only collects coverage. Step debugging needs Xdebug and isn't set up.

Herd Lite's Linux binary is static musl and cannot load extensions, and static-php-cli can only build PCOV as a loadable extension, so no Herd Lite build will ever include it. That is why the Linux setup scripts install PHP from apt instead:

- **Claude Code cloud sandboxes (Ubuntu):** `scripts/sandbox/setup.sh` installs `php8.4-*` and `php8.4-pcov` from the [ondrej/php PPA](https://launchpad.net/~ondrej/+archive/ubuntu/php).
- **Amp orbs (Debian 12):** `.agents/setup` installs the same packages from [packages.sury.org](https://packages.sury.org/php/), the Debian side of the same archive.
- **CI:** no driver needed. GitHub Actions sets `CI=true`, which turns TIA off, so CI keeps running the whole suite.

### macOS

The Herd Lite binary that mise installs has no PCOV either. Herd's documentation says its macOS binaries can load an extension compiled against Homebrew's PHP, so this should work, but it is unverified:

```bash
brew install php@8.4                         # only used to compile the extension
$(brew --prefix php@8.4)/bin/pecl install pcov
php --ini                                    # note "Scan for additional .ini files in"
echo "extension=$(brew --prefix)/lib/php/pecl/20240924/pcov.so" > <scan dir>/pcov.ini
php -m | grep pcov
```

If `php -m` doesn't list `pcov`, or PHP refuses to load it, the fallback is to run tests with Homebrew's `php@8.4` plus the same `pcov.so`, or to switch mise from Herd Lite to Homebrew PHP.

Check it works by running the suite twice: the second run's summary should say every test was `replayed`.
