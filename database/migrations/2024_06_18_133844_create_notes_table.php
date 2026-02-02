<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite requires CHECK constraint in CREATE TABLE
            DB::statement('
                CREATE TABLE notes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    slug TEXT NOT NULL UNIQUE,
                    title TEXT,
                    lead TEXT,
                    visible INTEGER NOT NULL DEFAULT 1,
                    published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    created_at DATETIME,
                    updated_at DATETIME,
                    markdown_content TEXT,
                    CHECK (title IS NOT NULL OR lead IS NOT NULL OR markdown_content IS NOT NULL)
                )
            ');
        } else {
            // PostgreSQL can use ALTER TABLE for CHECK constraint
            Schema::create('notes', function ($table) {
                $table->id();
                $table->text('slug')->unique();
                $table->text('title')->nullable();
                $table->text('lead')->nullable();
                $table->boolean('visible')->default(true);
                $table->timestamp('published_at')->useCurrent();
                $table->timestamps();
                $table->text('markdown_content')->nullable();
            });

            DB::statement('ALTER TABLE notes ADD CONSTRAINT check_not_all_text_null CHECK (title IS NOT NULL OR lead IS NOT NULL OR markdown_content IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
