<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `memories.month` becomes `month_id`, so `$memory->month` can be the
     * relationship to its row in `months`. The month's own check now
     * guarantees the first-of-month rule, so the memories copy of it goes.
     */
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->renameColumn('month', 'month_id');
        });

        DB::statement('ALTER TABLE memories RENAME CONSTRAINT memories_month_foreign TO memories_month_id_foreign');
        DB::statement('ALTER INDEX memories_user_id_month_unique RENAME TO memories_user_id_month_id_unique');
        DB::statement('ALTER TABLE memories DROP CONSTRAINT memories_month_is_first_of_month');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->renameColumn('month_id', 'month');
        });

        DB::statement('ALTER TABLE memories RENAME CONSTRAINT memories_month_id_foreign TO memories_month_foreign');
        DB::statement('ALTER INDEX memories_user_id_month_id_unique RENAME TO memories_user_id_month_unique');
        DB::statement('ALTER TABLE memories ADD CONSTRAINT memories_month_is_first_of_month CHECK (EXTRACT(DAY FROM month) = 1)');
    }
};
