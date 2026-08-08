<?php

use App\Support\DatabaseView;
use Illuminate\Database\Migrations\Migration;

/**
 * Move media_tracking_summary onto versioned `.sql` definition files.
 *
 * v1 is the definition the two preceding view migrations already left in the
 * database, so this is a no-op in behaviour — it exists to make the file the
 * source of truth from here on.
 */
return new class extends Migration
{
    public function up(): void
    {
        DatabaseView::apply('media_tracking_summary', 'v1');
    }

    public function down(): void
    {
        DatabaseView::apply('media_tracking_summary', 'v1');
    }
};
