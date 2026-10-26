<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removed reactions are soft-deleted so Undo can restore them, so one
     * person's emoji only needs to be unique among the reactions still there.
     */
    public function up(): void
    {
        Schema::table('memory_reactions', function (Blueprint $table) {
            $table->softDeletesTz();
            $table->dropUnique(['memory_id', 'user_id', 'emoji']);
        });

        DB::statement('CREATE UNIQUE INDEX memory_reactions_memory_id_user_id_emoji_unique ON memory_reactions (memory_id, user_id, emoji) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('memory_reactions')->whereNotNull('deleted_at')->delete();
        DB::statement('DROP INDEX memory_reactions_memory_id_user_id_emoji_unique');

        Schema::table('memory_reactions', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
            $table->unique(['memory_id', 'user_id', 'emoji']);
        });
    }
};
