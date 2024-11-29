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
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->text('slug', 300)->unique()->index();

            $table->text('title')->nullable();
            $table->text('lead')->nullable();
            $table->text('content')->nullable();

            $table->boolean('visible')->default(true);

            $table->timestampTz('published_at')->useCurrent();

            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE notes ADD CONSTRAINT check_not_all_text_null 
            CHECK (title IS NOT NULL OR lead IS NOT NULL OR content IS NOT NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
