# Development Environment Setup

Bootstrap a fresh development environment to run tests. This guide is designed for automated Claude Code and git worktree workflows.

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm
- PostgreSQL 15 or higher

## Setup Steps

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 3. PostgreSQL Setup

#### Check if PostgreSQL is Running

First, check if PostgreSQL is already running:

```bash
pg_isready
```

If PostgreSQL is not running, you have several options to start it depending on your environment:

**Option A - systemd (most Linux distributions):**
```bash
sudo systemctl start postgresql
```

**Option B - service command (older Linux/WSL):**
```bash
sudo service postgresql start
```

**Option C - Docker (if using Docker for development):**
```bash
docker-compose up -d postgres
```

**Option D - Homebrew (macOS):**
```bash
brew services start postgresql@15
```

#### Create Database and User (if needed)

Check if the database exists:

```bash
psql -U postgres -lqt | cut -d \| -f 1 | grep -qw laravel && echo "Database exists" || echo "Database does not exist"
```

If the database doesn't exist, create it:

```bash
sudo -u postgres psql -c "CREATE DATABASE laravel;"
sudo -u postgres psql -c "CREATE USER root WITH PASSWORD 'password';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE laravel TO root;"
sudo -u postgres psql -d laravel -c "GRANT ALL ON SCHEMA public TO root;"
```

**Note:** Adjust the commands based on your PostgreSQL authentication setup. If you're using Docker or a different user, modify the `-u postgres` part accordingly.

#### Run Migrations

```bash
php artisan migrate
```

### 4. Build Frontend Assets

```bash
npm run build
```

### 5. Run Tests

```bash
php artisan test
```

All tests should pass.
