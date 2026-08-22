-- One row per media item: its identity, its derived tracking state, and its
-- free text in three shapes.
--
-- current_status is the most recent non-comment event ('comment' events are
-- annotations, not state changes), defaulting to 'backlog' when an item has no
-- events at all.
--
-- The free text of an item lives in two places — media.note and a comment on
-- any of its events — and this view exposes it three ways, because they answer
-- different questions:
--
--   note       the item's standing note, verbatim. The only column that
--              isolates it from the event commentary.
--   full_text  note plus every event comment as one markdown string. The
--              searchable shape: one ILIKE covers all of an item's text, and
--              this is the column a full-text index would target.
--   history    every event as a jsonb array, including events carrying no
--              comment. The structured shape: what happened and when.
--              jsonb sorts object keys, which is fine — key order carries no
--              meaning here, and jsonb preserves the array order that does.
--
-- All three are ADMIN-ONLY, exactly as their sources are (MediaPolicy::seeNote).
-- Authorization is enforced in the application, so callers serving unauthorized
-- requests must select columns explicitly — see SearchMediaQuery::COLUMNS.
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
    agg.abandoned_at,
    m.creator_id,
    NULLIF(BTRIM(m.note, E' \t\r\n'), '')
                AS note,
    -- concat_ws skips NULL parts, so an item with only a note (or only event
    -- comments) gets no stray blank lines. NULLIF maps "no text at all" to NULL.
    NULLIF(
        concat_ws(
            E'\n\n',
            NULLIF(BTRIM(m.note, E' \t\r\n'), ''),
            notes.event_notes
        ),
        ''
    )           AS full_text,
    -- An empty array rather than NULL: callers iterate it, and an empty loop
    -- beats a null check. Unlike the text columns, where NULL means "nothing".
    COALESCE(hist.events, '[]'::jsonb)
                AS history
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
LEFT JOIN LATERAL (
    SELECT string_agg(
        FORMAT(
            '- **%s** (%s): %s',
            met.name,
            -- AT TIME ZONE 'UTC' so the rendered date matches the application
            -- timezone rather than whatever the Postgres session happens to be.
            TO_CHAR(me.occurred_at AT TIME ZONE 'UTC', 'YYYY-MM-DD'),
            -- Indent wrapped lines so a multi-paragraph comment stays inside
            -- its bullet instead of ending the markdown list.
            REGEXP_REPLACE(BTRIM(me.comment, E' \t\r\n'), '\r?\n', E'\n  ', 'g')
        ),
        -- id breaks ties so two events on the same timestamp order stably.
        E'\n' ORDER BY me.occurred_at, me.id
    )           AS event_notes
    FROM media_events me
    JOIN media_event_types met ON me.media_event_type_id = met.id
    WHERE me.media_id = m.id
      AND BTRIM(COALESCE(me.comment, ''), E' \t\r\n') != ''
) notes ON TRUE
LEFT JOIN LATERAL (
    -- Every event, comment or not: this is the timeline, and a started event
    -- with nothing said about it is still something that happened. That is
    -- what keeps history complementary to full_text rather than a re-encoding.
    SELECT jsonb_agg(
        jsonb_build_object(
            'type', met.name,
            'occurred_at', TO_CHAR(me.occurred_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS"+00:00"'),
            'comment', NULLIF(BTRIM(COALESCE(me.comment, ''), E' \t\r\n'), '')
        )
        ORDER BY me.occurred_at, me.id
    )           AS events
    FROM media_events me
    JOIN media_event_types met ON me.media_event_type_id = met.id
    WHERE me.media_id = m.id
) hist ON TRUE
