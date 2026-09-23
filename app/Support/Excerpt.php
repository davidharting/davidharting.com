<?php

namespace App\Support;

class Excerpt
{
    /**
     * The text surrounding the first case-insensitive match of $phrase, with
     * up to $radius characters of context on each side and an ellipsis
     * wherever the text was cut. Null when there is no text or no match.
     *
     * Laravel's Str::excerpt() does the same job, but its pattern does not
     * match across line breaks, so it returns null for any match past the
     * first line of multi-line text such as markdown.
     */
    public static function around(?string $text, string $phrase, int $radius = 120): ?string
    {
        if (! $text) {
            return null;
        }

        $position = mb_stripos($text, $phrase);

        if ($position === false) {
            return null;
        }

        $start = max(0, $position - $radius);
        $excerpt = mb_substr($text, $start, mb_strlen($phrase) + $radius * 2);

        return ($start > 0 ? '…' : '')
            .trim($excerpt)
            .($start + mb_strlen($excerpt) < mb_strlen($text) ? '…' : '');
    }
}
