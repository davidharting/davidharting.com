<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the participant closed the "How it works" card on the memories
     * list, kept on the server so it stays closed on every device.
     */
    public function up(): void
    {
        Schema::table('memory_participants', function (Blueprint $table) {
            $table->timestampTz('how_it_works_dismissed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memory_participants', function (Blueprint $table) {
            $table->dropColumn('how_it_works_dismissed_at');
        });
    }
};
