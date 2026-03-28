<?php

use App\Support\DatabaseView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite covering index for all the event subqueries in the view.
        Schema::table('media_events', function (Blueprint $table) {
            $table->index(
                ['media_id', 'media_event_type_id', 'occurred_at'],
                'media_events_tracking_summary_idx',
            );
        });

        DatabaseView::createOrReplace('media_tracking_summary');
    }

    public function down(): void
    {
        DatabaseView::warnOnRollback('media_tracking_summary');

        Schema::table('media_events', function (Blueprint $table) {
            $table->dropIndex('media_events_tracking_summary_idx');
        });
    }
};
