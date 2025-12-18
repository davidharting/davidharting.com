This is a Laravel project for my personal website, davidharting.com.

## Development Setup

If you're setting up a fresh development environment (e.g., new git worktree), follow the steps in [docs/development-setup.md](docs/development-setup.md) to bootstrap from zero to passing tests.

## Architecture overview

- Cloudflare DNS. Orange-checkmark reverse proxy to my Digital Ocean droplet
- Digital Ocean droplet is runnning unbuntu. It has docker installed
- I use `docker-compose up` to run the containers for my site. See docker-compose.yml
- I run the laravel web server Octane and Caddy. See Caddyfile.

So Cloudflare -> Digital Ocean droplet -> Caddy -> Laravel -> Postgres

The containers are:

- laravel web server
- laravel queue worker
- laravel scheduler
- postgres database

## Commands

- Use `ripgrep` to search files and `fd` to find files
- Use `php artisan` for Laravel commands
- Run tests often with `php artisan test`. Usually it's best to run one file at a time, which you can do by passing in the relative path to the test file as the first positional argument.
- Run `task format` to format code after making changes.

## Testing

We write tests for all changes
We focus on feature tests to get more leverage

## Way of working

- Work on only what I ask you to do, and one thing at a time. Focus changes to just the task at hand. Ask about refactors before doing them.
- Make atomic commits with detailed messages
- Include tests as you go rather than at the end. The tests should be committed with the relevant application changes.
