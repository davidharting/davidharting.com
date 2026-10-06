<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROTOTYPE (#251), variant A: a hand-rolled photo table.
 *
 * Files live at memories/{memory_id}/photos/{id}/{original.ext,web.jpg,thumb.jpg} on the private disk,
 * so only the original's extension needs storing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained();
            $table->unsignedSmallInteger('position');
            $table->text('caption')->nullable();
            $table->string('original_extension', 10);
            $table->string('original_mime_type');
            $table->unsignedInteger('original_size');
            $table->string('status');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->text('failure')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['memory_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_photos');
    }
};
