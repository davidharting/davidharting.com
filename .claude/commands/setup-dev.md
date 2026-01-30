# Setup Development Environment

Bootstrap a fresh development environment (e.g., new git worktree) to run tests.

## Prerequisites

Ensure these are available:
- PHP 8.2+
- Composer
- Node.js and npm
- PostgreSQL 15+

## Steps to Execute

Run these commands in order:

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment configuration
cp .env.example .env
php artisan key:generate

# 3. PostgreSQL setup
sudo service postgresql start
sudo -u postgres psql -c "CREATE DATABASE laravel;" 2>/dev/null || true
sudo -u postgres psql -c "CREATE USER root WITH PASSWORD 'password';" 2>/dev/null || true
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE laravel TO root;"
sudo -u postgres psql -d laravel -c "GRANT ALL ON SCHEMA public TO root;"

# 4. Database migrations
php artisan migrate

# 5. Build frontend assets
npm run build

# 6. Verify setup
php artisan test
```

## Success Criteria

All tests should pass. If tests fail, diagnose and fix before proceeding with other work.

## Reference

See [docs/development-setup.md](../docs/development-setup.md) for detailed manual instructions.
