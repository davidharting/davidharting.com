<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->text('markdown_content')->nullable()->after('content');
        });

        // Drop the old constraint
        DB::statement('ALTER TABLE notes DROP CONSTRAINT check_not_all_text_null');

        // Add new constraint that includes markdown_content
        DB::statement('ALTER TABLE notes ADD CONSTRAINT check_not_all_text_null
            CHECK (title IS NOT NULL OR lead IS NOT NULL OR content IS NOT NULL OR markdown_content IS NOT NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the updated constraint
        DB::statement('ALTER TABLE notes DROP CONSTRAINT check_not_all_text_null');

        // Restore the original constraint
        DB::statement('ALTER TABLE notes ADD CONSTRAINT check_not_all_text_null
            CHECK (title IS NOT NULL OR lead IS NOT NULL OR content IS NOT NULL)');

        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('markdown_content');
        });
    }
};
