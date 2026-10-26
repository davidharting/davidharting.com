<?php

namespace App\Support;

/**
 * Recognises emoji in text.
 */
final class Emoji
{
    /**
     * The longest text accepted as one emoji, in bytes. The longest
     * standard emoji sequences are well under it.
     */
    private const int MAX_BYTES = 64;

    /**
     * Whether the text is exactly one emoji: a flag, a keycap, or a
     * pictograph with any presentation selector, skin tone or joined
     * sequence. Stray combining marks are refused so nothing draws outside
     * the space one emoji takes.
     */
    public static function isOne(string $text): bool
    {
        if (strlen($text) > self::MAX_BYTES || preg_match('/^\X$/u', $text) !== 1) {
            return false;
        }

        if (preg_match('/\p{Regional_Indicator}/u', $text) === 1) {
            return preg_match('/^\p{Regional_Indicator}{2}$/u', $text) === 1;
        }

        if (str_contains($text, "\u{20E3}")) {
            return preg_match('/^[0-9#*]\x{FE0F}?\x{20E3}$/u', $text) === 1;
        }

        return preg_match('/^\p{Extended_Pictographic}/u', $text) === 1
            && preg_match('/[^\P{M}\x{FE0F}]/u', $text) === 0;
    }
}
