<?php

use App\Enum\MediaTypeName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (MediaTypeName::cases() as $mediaType) {
            DB::table('media_types')->insertOrIgnore([
                'name' => $mediaType->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Do nothing on rollback
    }
};
