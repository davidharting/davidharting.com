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
