<?php

use App\Models\Word;
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
