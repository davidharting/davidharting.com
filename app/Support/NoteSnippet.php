<?php

namespace App\Support;

use App\Models\Note;

class NoteSnippet
{
    /**
     * How many characters of context to include on each side of the first
     * match when building a snippet.
     */
    private const CHARS_AROUND_MATCH = 120;

    /**
     * The content surrounding the first match of $query in the note's
     * markdown, so a caller can judge relevance without fetching every note.
     * Null when the note has no body, or when the match was only in the title
     * or lead, which a search result already carries.
     */
    public static function for(Note $note, string $query): ?string
    {
        $content = $note->markdown_content;

        if (! $content) {
            return null;
        }

        $position = mb_stripos($content, $query);

        if ($position === false) {
            return null;
        }

        $start = max(0, $position - self::CHARS_AROUND_MATCH);
        $snippet = mb_substr($content, $start, mb_strlen($query) + self::CHARS_AROUND_MATCH * 2);

        return ($start > 0 ? '…' : '')
            .trim($snippet)
            .($start + mb_strlen($snippet) < mb_strlen($content) ? '…' : '');
    }
}
