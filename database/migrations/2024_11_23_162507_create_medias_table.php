<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('medias', function (Blueprint $table) {
            $table->id();
            $table->timestampsTz();
            $table->unsignedSmallInteger('media_type_id');
            $table->year('year')->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();

            $table->foreign('media_type_id')->references('id')->on('media_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medias');
    }
};
