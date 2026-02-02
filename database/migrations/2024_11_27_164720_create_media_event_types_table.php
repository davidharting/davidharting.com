<?php

use App\Enum\MediaEventTypeName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_event_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name')->unique();
            $table->timestamps();

            $table->index('name');
        });

        // Insert initial event types
        $now = now();
        foreach (MediaEventTypeName::cases() as $eventType) {
            DB::table('media_event_types')->insertOrIgnore([
                'name' => $eventType->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_event_types');
    }
};
