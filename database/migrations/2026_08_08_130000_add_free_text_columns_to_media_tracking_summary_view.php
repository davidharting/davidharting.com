<?php

use App\Support\DatabaseView;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseView::apply('media_tracking_summary', 'v2');
    }

    public function down(): void
    {
        DatabaseView::apply('media_tracking_summary', 'v1');
    }
};
