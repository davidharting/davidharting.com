<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name', 50);
            $table->char('scorecard_id', 26)->index();

            $table->foreign('scorecard_id')->references('id')->on('scorecards');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
