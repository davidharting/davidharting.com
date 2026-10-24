<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every memory's month must be a row in `months`.
     */
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->foreign('month')->references('id')->on('months')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropForeign(['month']);
        });
    }
};
