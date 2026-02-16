---
name: squash-migrations
description: Squash Laravel migrations into a schema dump while preserving data migrations.
---

# Squash Migrations

Squash all Laravel migrations into a schema dump, then create an idempotent data migration to preserve system/seed data that was established in the old migrations.

## Steps

1. **Identify data migrations**: Before squashing, review all existing migration files for any that insert data (look for `DB::table(...)->insert`, `DB::table(...)->upsert`, or similar). Note the tables and rows they insert.

2. **Run the schema dump**: `php artisan schema:dump --prune`

3. **Create an idempotent data migration**: Run `php artisan make:migration seed_system_data` and populate it with all the data inserts identified in step 1. Use `insertOrIgnore` instead of `insert` so the migration is safe to run against databases that already have the data (like production). This works because the lookup/type tables have unique constraints on `name`.

4. **Verify**: Run `php artisan test` to confirm everything still works.

## Important Notes

- The `--prune` flag deletes all migration files after dumping the schema. This is why data migrations are lost.
- `insertOrIgnore` relies on unique constraints to silently skip existing rows. Verify the target tables have appropriate unique constraints before using it.
- The `down()` method of the data migration should delete the specific rows by name, not truncate the tables.
- Check the app's Enum classes and Models to find the canonical list of system data values (e.g., `app/Enum/` directory).
