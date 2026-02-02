<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('media_event_type_id')->index();
            $table->foreignId('media_id')->index()->constrained('media')->cascadeOnDelete();
            $table->timestamps();
            $table->text('comment')->nullable();
            $table->timestamp('occurred_at');

            $table->foreign('media_event_type_id')->references('id')->on('media_event_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_events');
    }
};
