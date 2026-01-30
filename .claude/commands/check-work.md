# Check Your Work

Run this before considering a task complete.

## Checklist

### 1. Tests
- Did you add quality tests to cover the change?
- Are you testing behavior, not implementation details?
- Do all tests pass? Run: `php artisan test`

### 2. Database Seeder
Review if anything should be added to `DatabaseSeeder.php`:
- New model types
- New configurations of existing models
- New visual states that need seed data for development/demos

### 3. Code Quality
- Run `task format` to format all code
- Review for any obvious issues or leftover debug code

### 4. Commit Hygiene
- Are changes atomic and well-described?
- Did you include tests with the relevant application changes (not in a separate commit)?

## Instructions

Go through each item above and report what was checked and any actions taken.
