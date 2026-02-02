# Development Environment Setup

Bootstrap a fresh development environment. This guide covers two approaches:
1. **Quick Start** - Uses Docker for Postgres, ideal for most development
2. **Manual Setup** - Native PostgreSQL installation, for when Docker isn't available

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm
- Docker (for Quick Start) OR PostgreSQL 15+ (for Manual Setup)

Run `task dev:preflight` to verify your environment has all required dependencies.

## Quick Start (Recommended)

This approach runs PHP and Node natively but uses a Docker container for Postgres.

### 1. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 2. Start Development Server

```bash
task dev:serve
```

This single command:
- Verifies all dependencies are available
- Installs PHP and Node dependencies
- Starts a Postgres container on a random port
- Runs database migrations and seeding
- Starts the PHP development server, Vite, and queue worker in parallel

Press `Ctrl+C` to stop all services. The Postgres container is automatically cleaned up.

### 3. Run Tests

In a separate terminal:

```bash
php artisan test
```

## Manual Setup (Native PostgreSQL)

Use this approach if you don't have Docker or prefer a native PostgreSQL installation.

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

## Available Tasks

| Command | Description |
|---------|-------------|
| `task dev:preflight` | Check that all development dependencies are available |
| `task dev:serve` | Start local development environment |
| `task dev:install` | Install PHP and Node dependencies |
| `task dev:db:start` | Start standalone Postgres container |
| `task dev:db:stop` | Stop standalone Postgres container |
| `task dev:db:refresh` | Reset database with fresh migrations and seeding |
