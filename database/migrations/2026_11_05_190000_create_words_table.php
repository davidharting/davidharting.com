<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->text('word');
            $table->text('short_definition');
            $table->text('full_definition')->nullable();
            $table->timestamps();
        });

        // One row per word whatever its case, so "Ephemeral" and "ephemeral"
        // cannot both be recorded.
        DB::statement('CREATE UNIQUE INDEX words_word_lower_unique ON words (lower(word))');
    }

    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
