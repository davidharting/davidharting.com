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

Start PostgreSQL:

```bash
sudo service postgresql start
```

Create database and user:

```bash
sudo -u postgres psql -c "CREATE DATABASE laravel;"
sudo -u postgres psql -c "CREATE USER root WITH PASSWORD 'password';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE laravel TO root;"
sudo -u postgres psql -d laravel -c "GRANT ALL ON SCHEMA public TO root;"
```

Run migrations:

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
