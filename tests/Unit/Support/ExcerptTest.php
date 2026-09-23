<?php

use App\Support\Excerpt;

describe('around()', function () {
    test('returns the text around the first match, with ellipses where it was cut', function () {
        $text = str_repeat('a', 20).' xylophone '.str_repeat('b', 20);

        expect(Excerpt::around($text, 'xylophone', context: 5))->toBe('…aaaa xylophone bbbb…');
    });

    test('matches past the first line of multi-line text', function () {
        $text = "# Heading\n\nI bought a xylophone.\n\nThe end.";

        expect(Excerpt::around($text, 'xylophone', context: 5))->toBe("…ht a xylophone.\n\nTh…");
    });

    test('matches case-insensitively', function () {
        expect(Excerpt::around('I bought a Xylophone.', 'xylophone'))->toBe('I bought a Xylophone.');
    });

    test('omits the ellipses when the whole text fits', function () {
        expect(Excerpt::around('A short body about a xylophone.', 'xylophone'))->toBe('A short body about a xylophone.');
    });

    test('is null when the text does not contain the phrase', function () {
        expect(Excerpt::around('Nothing to see here.', 'xylophone'))->toBeNull();
    });

    test('is null when there is no text', function () {
        expect(Excerpt::around(null, 'xylophone'))->toBeNull();
    });
});
