<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies Postgres view definitions that live as versioned `.sql` files under
 * `database/views/{view}/{version}.sql`.
 *
 * Each file holds only the `SELECT` body — this class owns the view name, so a
 * file can never disagree with the view it defines. Changing a view means
 * adding the next version file and a migration that applies it on `up()` and
 * the previous version on `down()`, which keeps the diff to readable SQL
 * instead of a heredoc pasted into a migration.
 */
class DatabaseView
{
    /**
     * (Re)create a view from its versioned definition file.
     *
     * Drops and recreates rather than `CREATE OR REPLACE`, which in Postgres
     * cannot rename, reorder, or retype existing columns — a restriction that
     * would otherwise make rolling back to an earlier version fail.
     */
    public static function apply(string $view, string $version): void
    {
        $select = self::definition($view, $version);

        self::drop($view);
        DB::statement("CREATE VIEW {$view} AS {$select}");
    }

    /**
     * Drop a view.
     *
     * Postgres refuses to `DROP COLUMN` or `ALTER COLUMN ... TYPE` on a column a
     * view selects, so a migration doing either must drop the view first and
     * reapply the next version afterwards.
     */
    public static function drop(string $view): void
    {
        DB::statement("DROP VIEW IF EXISTS {$view}");
    }

    /**
     * Read the `SELECT` body for one version of a view.
     */
    public static function definition(string $view, string $version): string
    {
        $path = self::path($view, $version);

        if (! is_file($path)) {
            throw new RuntimeException("No definition for view [{$view}] version [{$version}] at [{$path}].");
        }

        $select = trim((string) file_get_contents($path));

        if ($select === '') {
            throw new RuntimeException("Definition for view [{$view}] version [{$version}] is empty.");
        }

        return rtrim($select, ';');
    }

    public static function path(string $view, string $version): string
    {
        return database_path("views/{$view}/{$version}.sql");
    }
}
