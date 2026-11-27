<?php

use App\Models\Word;
use App\Queries\SearchWordsQuery;
use Tests\TestCase;

/**
 * The words a search for $search finds on its first page, in order, each
 * with whether it matched on its spelling.
 *
 * @return array<string, bool>
 */
function wordsFoundFor(string $search): array
{
    return (new SearchWordsQuery($search))->paginate(perPage: 50, page: 1)
        ->mapWithKeys(fn (Word $word): array => [$word->word => $word->matches_word])
        ->all();
}

describe('paginate()', function () {
    test('ranks the word itself, then words starting with the search, then words containing it, then definition matches', function () {
        /** @var TestCase $this */
        // Created out of order, so the ranking has to do the work.
        Word::factory()->create(['word' => 'scant']);
        Word::factory()->create(['word' => 'jargon', 'short_definition' => 'cant; argot']);
        Word::factory()->create(['word' => 'cantata']);
        Word::factory()->create(['word' => 'Cant']);
        Word::factory()->create(['word' => 'decant']);
        Word::factory()->create(['word' => 'cantankerous']);

        expect(array_keys(wordsFoundFor('cant')))
            ->toBe(['Cant', 'cantankerous', 'cantata', 'decant', 'scant', 'jargon']);
    });

    test('marks whether each word matched on its spelling or only a definition', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'canny', 'short_definition' => 'shrewd']);
        Word::factory()->create(['word' => 'astute', 'short_definition' => 'canny; sharp']);

        expect(wordsFoundFor('canny'))->toBe(['canny' => true, 'astute' => false]);
    });

    test('searches the full definition too', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'transient', 'short_definition' => 'brief', 'full_definition' => 'Synonyms: ephemeral']);
        Word::factory()->create(['word' => 'liminal', 'short_definition' => 'at a threshold', 'full_definition' => null]);

        expect(wordsFoundFor('ephemeral'))->toBe(['transient' => false]);
    });

    test('ignores spaces around the search', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'canny']);

        expect(wordsFoundFor('  canny '))->toBe(['canny' => true]);
    });

    test('refuses a blank search, which would match every word', function () {
        /** @var TestCase $this */
        expect(fn () => new SearchWordsQuery('   '))->toThrow(InvalidArgumentException::class);
    });
});
