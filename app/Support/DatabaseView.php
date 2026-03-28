<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseView
{
    public static function createOrReplace(string $viewName): void
    {
        $sql = file_get_contents(database_path("views/{$viewName}.sql"));
        DB::statement("CREATE OR REPLACE VIEW {$viewName} AS\n{$sql}");
    }

    public static function warnOnRollback(string $viewName): void
    {
        Log::warning("View migration rollback is a no-op for '{$viewName}'. Run migrate:fresh to rebuild.");
    }
}
