<?php

use App\Models\Word;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

describe('word', function () {
    test('is stored without surrounding whitespace', function () {
        /** @var TestCase $this */
        expect(Word::factory()->create(['word' => "  ephemeral\n"])->refresh()->word)->toBe('ephemeral');
    });

    test('cannot be recorded twice in different case', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'ephemeral']);

        Word::factory()->create(['word' => 'Ephemeral']);
    })->throws(UniqueConstraintViolationException::class);

    test('longer than 255 characters is refused by the database', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => str_repeat('a', 256)]);
    })->throws(QueryException::class, 'words_word_length_check');
});

describe('findDuplicate()', function () {
    test('finds the recorded word whatever the case or surrounding whitespace', function () {
        /** @var TestCase $this */
        $ephemeral = Word::factory()->create(['word' => 'ephemeral']);

        expect(Word::findDuplicate(' Ephemeral ')?->is($ephemeral))->toBeTrue();
    });

    test('finds nothing for a new word', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'ephemeral']);

        expect(Word::findDuplicate('laconic'))->toBeNull();
    });

    test('does not report the word being edited as a duplicate of itself', function () {
        /** @var TestCase $this */
        $ephemeral = Word::factory()->create(['word' => 'ephemeral']);

        expect(Word::findDuplicate('Ephemeral', ignoring: $ephemeral))->toBeNull();
    });
});

describe('dictionaryLetter()', function () {
    test('files a word under its first letter, capitalised and without accents', function (string $word, string $letter) {
        /** @var TestCase $this */
        expect(Word::factory()->make(['word' => $word])->dictionaryLetter())->toBe($letter);
    })->with([
        'lowercase' => ['ephemeral', 'E'],
        'capitalised' => ['Laconic', 'L'],
        'accented' => ['éclat', 'E'],
    ]);

    test('files a word that starts with a digit or symbol under #', function (string $word) {
        /** @var TestCase $this */
        expect(Word::factory()->make(['word' => $word])->dictionaryLetter())->toBe('#');
    })->with(['a digit' => '24/7', 'a symbol' => '-ism']);
});

describe('renderFullDefinition()', function () {
    test('renders markdown and escapes raw HTML', function () {
        /** @var TestCase $this */
        $word = Word::factory()->make(['full_definition' => "**adjective**\n\n<script>alert(1)</script>"]);

        expect($word->renderFullDefinition())
            ->toContain('<strong>adjective</strong>')
            ->toContain('&lt;script&gt;')
            ->not->toContain('<script>');
    });

    test('drops javascript links', function () {
        /** @var TestCase $this */
        $word = Word::factory()->make(['full_definition' => '[see also](javascript:alert(1))']);

        expect($word->renderFullDefinition())->not->toContain('javascript:');
    });

    test('is null without a full definition', function () {
        /** @var TestCase $this */
        expect(Word::factory()->make(['full_definition' => null])->renderFullDefinition())->toBeNull();
    });
});
