This is a Laravel project for my personal website, davidharting.com.

## Development Setup

If you're setting up a fresh development environment (e.g., new git worktree), follow the steps in [docs/development-setup.md](docs/development-setup.md) to bootstrap from zero to passing tests.

## Architecture overview

- Cloudflare DNS. Orange-checkmark reverse proxy to my Digital Ocean droplet
- Digital Ocean droplet is running Ubuntu with Docker installed
- I use `docker-compose up` to run the containers for my site. See docker-compose.yml
- I run the Laravel web server Octane and Caddy. See Caddyfile.

So Cloudflare -> Digital Ocean droplet -> Caddy -> Laravel -> Postgres

The containers are:

- laravel web server
- laravel queue worker
- laravel scheduler
- postgres database

## Commands

- Use `ripgrep` to search files and `fd` to find files
- Use `php artisan` for Laravel commands
- Run tests: `php artisan test` (pass a file path to run one file)
- Format code: `task format`

## Rules

### Way of working

- Work on only what I ask you to do, and one thing at a time. Focus changes to just the task at hand. Ask about refactors before doing them.
- Make atomic commits with detailed messages
- Include tests as you go rather than at the end. Tests should be committed with the relevant application changes.

### Testing

- Write tests for all changes
- Focus on feature tests to get more leverage

### Pre-commit checklist

Before each commit, always:
1. Run `task format`
2. Run `php artisan test` and ensure tests pass

### Blade templates and styling

In `.blade.php` files, use [Daisy UI v5](https://daisyui.com/) components. Most styling should use basic Daisy components. Limit custom styling to controlling spacing.

## Slash Commands

Custom workflows are available in `.claude/commands/`:
- `/project:setup-dev` - Bootstrap a fresh development environment
- `/project:check-work` - Review checklist before completing a task
- `/project:make-pr` - Create a pull request with proper formatting
