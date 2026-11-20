<?php

use App\Clients\MerriamWebster\Entry;
use App\Clients\MerriamWebster\MerriamWebsterFailed;

describe('fromResponse()', function () {
    test('maps the fields Merriam-Webster sends and keeps the whole entry', function () {
        $raw = [
            'meta' => ['id' => 'bow:2', 'stems' => ['bow', 'bows'], 'offensive' => false],
            'hom' => 2,
            'hwi' => ['hw' => 'bow'],
            'fl' => 'noun',
            'shortdef' => ['a weapon for shooting arrows', 'a knot with loops'],
        ];

        $entry = Entry::fromResponse($raw);

        expect($entry->id)->toBe('bow:2')
            ->and($entry->headword)->toBe('bow')
            ->and($entry->homograph)->toBe(2)
            ->and($entry->partOfSpeech)->toBe('noun')
            ->and($entry->stems)->toBe(['bow', 'bows'])
            ->and($entry->shortDefinitions)->toBe(['a weapon for shooting arrows', 'a knot with loops'])
            ->and($entry->isOffensive)->toBeFalse()
            ->and($entry->raw)->toBe($raw);
    });

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

    test('refuses an entry without its id or headword', function (array $raw) {
        expect(fn () => Entry::fromResponse($raw))
            ->toThrow(MerriamWebsterFailed::class);
    })->with([
        'no id' => [['hwi' => ['hw' => 'limn']]],
        'no headword' => [['meta' => ['id' => 'limn']]],
    ]);
});
