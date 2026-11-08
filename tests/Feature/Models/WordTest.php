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
});

test('the same word cannot be recorded twice in different case', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    Word::factory()->create(['word' => 'Ephemeral']);
})->throws(UniqueConstraintViolationException::class);

test('a word longer than 255 characters is refused by the database', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => str_repeat('a', 256)]);
})->throws(QueryException::class, 'words_word_length_check');

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
