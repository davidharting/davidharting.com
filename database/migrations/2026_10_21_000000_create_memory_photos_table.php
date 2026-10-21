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
            $table->text('web_path')->nullable();
            $table->text('web_mime_type')->nullable();
            $table->unsignedInteger('web_width')->nullable();
            $table->unsignedInteger('web_height')->nullable();
            $table->unsignedBigInteger('web_bytes')->nullable();
            $table->text('thumbnail_path')->nullable();
            $table->text('thumbnail_mime_type')->nullable();
            $table->unsignedInteger('thumbnail_width')->nullable();
            $table->unsignedInteger('thumbnail_height')->nullable();
            $table->unsignedBigInteger('thumbnail_bytes')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('failure')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['memory_id', 'position']);
        });

        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_position_not_negative CHECK (position >= 0)');
        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_original_not_empty CHECK (original_bytes > 0)');
        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_failure_has_reason CHECK ((failed_at IS NULL) = (failure IS NULL))');
        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_web_copy_complete CHECK (num_nulls(web_path, web_mime_type, web_width, web_height, web_bytes) IN (0, 5))');
        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_thumbnail_copy_complete CHECK (num_nulls(thumbnail_path, thumbnail_mime_type, thumbnail_width, thumbnail_height, thumbnail_bytes) IN (0, 5))');
        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_copies_made_together CHECK ((web_path IS NULL) = (thumbnail_path IS NULL))');
        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_copies_not_empty CHECK (web_width > 0 AND web_height > 0 AND web_bytes > 0 AND thumbnail_width > 0 AND thumbnail_height > 0 AND thumbnail_bytes > 0)');
        DB::statement('ALTER TABLE memory_photos ADD CONSTRAINT memory_photos_ready_or_failed CHECK (web_path IS NULL OR failed_at IS NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_photos');
    }
};
