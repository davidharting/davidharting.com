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
        Schema::create('media_event_types', function (Blueprint $table) {
            $table->smallIncrements('id')->primary();
            $table->string('name', 255);
            $table->timestampsTz();

            $table->unique('name');
            $table->index('name');
        });

        DB::table('media_event_types')->insert([
            ['name' => 'started', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'finished', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'abandoned', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_event_types');
    }
};
