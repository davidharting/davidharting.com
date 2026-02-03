<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Media types (insertOrIgnore for idempotency - production already has this data)
        DB::table('media_types')->insertOrIgnore([
            ['name' => 'book', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'movie', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'album', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'tv show', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'video game', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Media event types (insertOrIgnore for idempotency - production already has this data)
        DB::table('media_event_types')->insertOrIgnore([
            ['name' => 'started', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'finished', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'abandoned', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'comment', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('media_event_types')->truncate();
        DB::table('media_types')->truncate();
    }
};
