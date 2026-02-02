<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->text('slug')->unique();
            $table->text('title')->nullable();
            $table->text('lead')->nullable();
            $table->boolean('visible')->default(true);
            $table->timestamp('published_at')->useCurrent();
            $table->timestamps();
            $table->text('markdown_content')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
