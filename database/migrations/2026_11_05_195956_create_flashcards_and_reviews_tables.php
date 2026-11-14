<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            // What the flashcard quizzes: a Word today. One flashcard each.
            $table->string('quizzable_type');
            $table->unsignedBigInteger('quizzable_id');
            $table->unique(['quizzable_type', 'quizzable_id']);

            // The FSRS schedule, one column per App\Fsrs\CardSchedule field.
            $table->unsignedTinyInteger('state');
            $table->unsignedSmallInteger('step')->nullable();
            $table->double('stability')->nullable();
            $table->double('difficulty')->nullable();
            $table->timestampTz('due', 6)->index();
            $table->timestampTz('last_review', 6)->nullable();

            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flashcard_id')->constrained();
            $table->unsignedTinyInteger('rating');
            $table->timestampTz('reviewed_at', 6);
            $table->unsignedInteger('review_duration_ms')->nullable();
            // The schedule either side of the review, for debugging and replay.
            $table->jsonb('schedule_before');
            $table->jsonb('schedule_after');
            $table->timestampsTz(6);

            $table->index(['flashcard_id', 'reviewed_at']);
        });

        // Words recorded before flashcards existed get one, due now, like a
        // newly recorded word.
        DB::statement(<<<'SQL'
            INSERT INTO flashcards (quizzable_type, quizzable_id, state, step, due, created_at, updated_at)
            SELECT 'word', id, 1, 0, now(), now(), now() FROM words
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('flashcards');
    }
};
