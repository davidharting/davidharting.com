<?php

use App\Support\DatabaseView;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseView::createOrReplace('media_tracking_summary');
    }

    public function down(): void
    {
        DatabaseView::warnOnRollback('media_tracking_summary');
    }
};
