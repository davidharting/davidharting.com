<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A flashcard's learning status (App\Enum\LearningStatus), worked out by
     * Postgres from its FSRS schedule so the rule lives in exactly one place
     * and can be filtered, counted and indexed. The first that fits wins:
     *
     * - new: never reviewed.
     * - mastered: stability of 21 days or more.
     * - troublesome: not mastered, and difficulty 7 or higher (of 1 to 10).
     * - learning: anything else.
     *
     * The 21 and 7 are starting guesses. To retune them, add a migration that
     * runs ALTER TABLE flashcards ALTER COLUMN learning_status SET EXPRESSION
     * AS (...) (Postgres 17+).
     *
     * STORED because virtual generated columns arrive in Postgres 18. Once
     * production is on 18, a migration can drop this index and column and add
     * the column back as VIRTUAL: Postgres can't switch one in place, and 18
     * can't index a virtual column.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE flashcards ADD COLUMN learning_status varchar(16) NOT NULL
            GENERATED ALWAYS AS (
                CASE
                    WHEN last_review IS NULL THEN 'new'
                    WHEN stability >= 21 THEN 'mastered'
                    WHEN difficulty >= 7 THEN 'troublesome'
                    ELSE 'learning'
                END
            ) STORED
            SQL);

        DB::statement('CREATE INDEX flashcards_learning_status_index ON flashcards (learning_status)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE flashcards DROP COLUMN learning_status');
    }
};
