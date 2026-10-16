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
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('month');
            $table->text('body');
            $table->timestampTz('shared_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['user_id', 'month']);
        });

        DB::statement('ALTER TABLE memories ADD CONSTRAINT memories_month_is_first_of_month CHECK (EXTRACT(DAY FROM month) = 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
