<?php

use App\Models\Word;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

describe('senses', function () {
    test('fills in missing keys and drops blank examples', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create([
            'senses' => [
                'filament-uuid' => ['definition' => 'lasting a very short time', 'examples' => ['an ephemeral fashion', '']],
                ['part_of_speech' => '', 'definition' => 'a short-lived plant'],
            ],
        ]);

        expect($word->refresh()->senses)->toBe([
            ['part_of_speech' => null, 'definition' => 'lasting a very short time', 'examples' => ['an ephemeral fashion']],
            ['part_of_speech' => null, 'definition' => 'a short-lived plant', 'examples' => []],
        ]);
    });

    test('is an empty list when none were given', function () {
        /** @var TestCase $this */
        $word = Word::create(['word' => 'ephemeral']);

        expect($word->refresh()->senses)->toBe([]);
    });
});

describe('word', function () {
    test('is stored without surrounding whitespace', function () {
        /** @var TestCase $this */
        expect(Word::factory()->create(['word' => "  ephemeral\n"])->refresh()->word)->toBe('ephemeral');
    });
});

test('saving a word whose senses have not changed does not rewrite them', function () {
    /** @var TestCase $this */
    $word = Word::factory()->create()->refresh();

    $word->senses = $word->senses;

    expect($word->isDirty('senses'))->toBeFalse();
});

test('the same word cannot be recorded twice in different case', function () {
    /** @var TestCase $this */
    Word::factory()->create(['word' => 'ephemeral']);

    Word::factory()->create(['word' => 'Ephemeral']);
})->throws(UniqueConstraintViolationException::class);
