<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_events', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('media_event_type_id');
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();
            $table->text('comment')->nullable();
            $table->timestampTz('occurred_at');

            $table->index('media_id');
            $table->foreign('media_event_type_id')->references('id')->on('media_event_types');
            $table->index('media_event_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_events');
    }
};
