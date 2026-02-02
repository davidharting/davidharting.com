# Development Environment Setup

Bootstrap a fresh development environment to run tests. This guide is designed for automated Claude Code and git worktree workflows.

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm

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

### 3. Build Frontend Assets

```bash
npm run build
```

### 4. Run Tests

```bash
php artisan test
```

All tests should pass. Tests run against an in-memory SQLite database for speed.
