---
name: check-work
description: Pre-commit verification. Use before making any git commit to ensure code quality.
---

# Pre-Commit Checks

Run these checks before every commit.

## Required Steps

1. **Format code**: Run `task format`
2. **Run tests**: Run `php artisan test` and ensure all tests pass
3. **Review for debug code**: Check for leftover `dd()`, `dump()`, `console.log()`, etc.

## Commit Quality

- Keep commits atomic - one logical change per commit
- Write detailed commit messages explaining the "why"
- Include tests with the application changes they cover (not in separate commits)

## Database Seeder Check

Consider if `DatabaseSeeder.php` needs updates:

- New model types that should have seed data
- New configurations of existing models
- New visual states useful for development/demos

## After Checks Pass

If all checks pass, create the commit automatically. Write a detailed commit message explaining the "why" behind the changes.
