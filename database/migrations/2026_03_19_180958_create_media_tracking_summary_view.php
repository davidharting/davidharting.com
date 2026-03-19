<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW media_tracking_summary AS
            SELECT
                m.id                                                AS media_id,
                m.title,
                m.year,
                mt.name                                             AS media_type,
                c.name                                              AS creator,
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
                )                                                   AS current_status,
                (
                    SELECT me.occurred_at
                    FROM media_events me
                    JOIN media_event_types met ON me.media_event_type_id = met.id
                    WHERE me.media_id = m.id
                      AND met.name = 'started'
                    ORDER BY me.occurred_at ASC
                    LIMIT 1
                )                                                   AS started_at,
                (
                    SELECT me.occurred_at
                    FROM media_events me
                    JOIN media_event_types met ON me.media_event_type_id = met.id
                    WHERE me.media_id = m.id
                      AND met.name = 'finished'
                    ORDER BY me.occurred_at DESC
                    LIMIT 1
                )                                                   AS finished_at,
                (
                    SELECT me.occurred_at
                    FROM media_events me
                    JOIN media_event_types met ON me.media_event_type_id = met.id
                    WHERE me.media_id = m.id
                      AND met.name = 'abandoned'
                    ORDER BY me.occurred_at DESC
                    LIMIT 1
                )                                                   AS abandoned_at
            FROM media m
            LEFT JOIN media_types mt ON m.media_type_id = mt.id
            LEFT JOIN creators c ON m.creator_id = c.id
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS media_tracking_summary');
    }
};
