<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed system data for media types and media event types.
     * Uses insertOrIgnore so this is safe to run on databases that already have this data.
     */
    public function up(): void
    {
        DB::table('media_types')->insertOrIgnore([
            ['name' => 'book', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'movie', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'album', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'tv show', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'video game', 'created_at' => now(), 'updated_at' => now()],
        ]);

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
        DB::table('media_event_types')->whereIn('name', ['started', 'finished', 'abandoned', 'comment'])->delete();
        DB::table('media_types')->whereIn('name', ['book', 'movie', 'album', 'tv show', 'video game'])->delete();
    }
};
