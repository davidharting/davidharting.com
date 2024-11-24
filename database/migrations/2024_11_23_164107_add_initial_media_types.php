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
        DB::table('media_types')->insert([
            ['name' => 'book', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'movie', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'album', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'tv show', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('media_types')->truncate();
    }
};
