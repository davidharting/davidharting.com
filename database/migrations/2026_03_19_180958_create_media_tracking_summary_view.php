<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        DB::statement(<<<'SQL'
            CREATE VIEW media_tracking_summary AS
            SELECT
                m.id        AS media_id,
                m.title,
                m.year,
                mt.name     AS media_type,
                c.name      AS creator,
                COALESCE(
                    (
                        SELECT met.name
                        FROM media_events me
                        JOIN media_event_types met ON me.media_event_type_id = met.id
                        WHERE me.media_id = m.id
                          AND met.name != 'comment'
                        ORDER BY me.occurred_at DESC
                        LIMIT 1
                    ),
                    'backlog'
                )           AS current_status,
                agg.started_at,
                agg.finished_at,
                agg.abandoned_at
            FROM media m
            LEFT JOIN media_types mt ON m.media_type_id = mt.id
            LEFT JOIN creators c ON m.creator_id = c.id
            LEFT JOIN LATERAL (
                SELECT
                    -- ASC: we want the earliest start date, not the most recent restart
                    MIN(me.occurred_at) FILTER (WHERE met.name = 'started')   AS started_at,
                    MAX(me.occurred_at) FILTER (WHERE met.name = 'finished')  AS finished_at,
                    MAX(me.occurred_at) FILTER (WHERE met.name = 'abandoned') AS abandoned_at
                FROM media_events me
                JOIN media_event_types met ON me.media_event_type_id = met.id
                WHERE me.media_id = m.id
            ) agg ON TRUE
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS media_tracking_summary');

        Schema::table('media_events', function (Blueprint $table) {
            $table->dropIndex('media_events_tracking_summary_idx');
        });
    }
};
