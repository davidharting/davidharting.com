<?php

use App\Clients\MerriamWebster\Entry;
use App\Clients\MerriamWebster\MerriamWebsterFailed;

describe('fromResponse()', function () {
    test('drops the asterisks Merriam-Webster uses to mark syllable breaks', function () {
        $entry = Entry::fromResponse([
            'meta' => ['id' => 'apple'],
            'hwi' => ['hw' => 'ap*ple'],
        ]);

        expect($entry->headword)->toBe('apple');
    });

    test('leaves optional fields empty when the entry omits them', function () {
        $entry = Entry::fromResponse([
            'meta' => ['id' => 'ad hoc'],
            'hwi' => ['hw' => 'ad hoc'],
        ]);

        expect($entry->homograph)->toBeNull()
            ->and($entry->partOfSpeech)->toBeNull()
            ->and($entry->stems)->toBe([])
            ->and($entry->shortDefinitions)->toBe([])
            ->and($entry->isOffensive)->toBeFalse();
    });

    test('flags an entry Merriam-Webster labels offensive', function () {
        $entry = Entry::fromResponse([
            'meta' => ['id' => 'slur', 'offensive' => true],
            'hwi' => ['hw' => 'slur'],
        ]);

        expect($entry->isOffensive)->toBeTrue();
    });

    test('refuses an entry without an id', function () {
        expect(fn () => Entry::fromResponse(['hwi' => ['hw' => 'limn']]))
            ->toThrow(MerriamWebsterFailed::class);
    });
});
