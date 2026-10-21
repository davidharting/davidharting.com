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
        Schema::create('memory_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('caption')->nullable();
            $table->text('original_path')->unique();
            $table->text('original_mime_type');
            $table->unsignedBigInteger('original_bytes');
            $table->jsonb('variants')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('failure')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['memory_id', 'position']);
        });

        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_position_not_negative CHECK (position >= 0)');
        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_original_not_empty CHECK (original_bytes > 0)');
        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_failure_has_reason CHECK ((failed_at IS NULL) = (failure IS NULL))');
        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_ready_or_failed CHECK (variants IS NULL OR failed_at IS NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_photos');
    }
};
