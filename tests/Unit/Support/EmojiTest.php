<?php

use App\Support\Emoji;

describe('isEmoji()', function () {
    test('accepts a single emoji', function (string $emoji) {
        expect(Emoji::isEmoji($emoji))->toBeTrue();
    })->with([
        'a pictograph' => '😂',
        'a pictograph with its presentation selector' => '❤️',
        'a skin tone' => '👍🏽',
        'a joined family' => '👨‍👩‍👧',
        'a country flag' => '🇺🇸',
        'a subdivision flag' => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
        'a keycap' => '1️⃣',
    ]);

    test('refuses anything that is not exactly one emoji', function (string $text) {
        expect(Emoji::isEmoji($text))->toBeFalse();
    })->with([
        'nothing' => '',
        'a letter' => 'a',
        'a word' => 'lol',
        'two emoji' => '❤️❤️',
        'an emoji and a space' => '❤️ ',
        'an emoji and a letter' => '😂a',
        'half a flag' => '🇦',
        'a letter in a keycap' => "a\u{20E3}",
        'an emoji under stacked accents' => '😀'.str_repeat("\u{0301}", 10),
        'too long to be one emoji' => '😀'.str_repeat("\u{200D}😀", 20),
    ]);
});
