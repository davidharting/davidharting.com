<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedSmallInteger('media_type_id');
            $table->foreignId('creator_id')->nullable()->constrained('creators')->cascadeOnDelete();
            $table->integer('year')->nullable();
            $table->string('title');
            $table->text('note')->nullable();

            $table->foreign('media_type_id')->references('id')->on('media_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
