<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop in order respecting foreign key constraints
        Schema::dropIfExists('scores');
        Schema::dropIfExists('players');
        Schema::dropIfExists('scorecards');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible - the feature is being removed
    }
};
