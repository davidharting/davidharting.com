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

        // The forms and MCP tools cap a word at 255 characters; this backs
        // them up for anything that writes to the table another way.
        DB::statement('ALTER TABLE words ADD CONSTRAINT words_word_length_check CHECK (char_length(word) <= 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
