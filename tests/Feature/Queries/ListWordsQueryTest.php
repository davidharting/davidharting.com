<?php

use App\Enum\LearningStatus;
use App\Enum\WordSort;
use App\Models\Word;
use App\Queries\ListWordsQuery;
use Tests\TestCase;

/**
 * The words the query lists on its first page, in order.
 *
 * @return list<string>
 */
function wordsListedBy(ListWordsQuery $query): array
{
    return $query->paginate(perPage: 50, page: 1)->pluck('word')->all();
}

describe('paginate()', function () {
    test('ranks a best match search: the word itself, words starting with it, words containing it, then definition matches', function () {
        /** @var TestCase $this */
        // Created out of order, so the ranking has to do the work.
        Word::factory()->create(['word' => 'scant']);
        Word::factory()->create(['word' => 'jargon', 'short_definition' => 'cant; argot']);
        Word::factory()->create(['word' => 'cantata']);
        Word::factory()->create(['word' => 'Cant']);
        Word::factory()->create(['word' => 'decant']);
        Word::factory()->create(['word' => 'cantankerous']);

        expect(wordsListedBy(new ListWordsQuery(search: 'cant', sort: WordSort::BestMatch)))
            ->toBe(['Cant', 'cantankerous', 'cantata', 'decant', 'scant', 'jargon']);
    });

    test('lists spelling matches before definition matches whatever the sort', function () {
        /** @var TestCase $this */
        $this->travelTo('2026-11-01');
        Word::factory()->create(['word' => 'canny', 'short_definition' => 'shrewd']);
        $this->travelTo('2026-11-02');
        Word::factory()->create(['word' => 'astute', 'short_definition' => 'canny; sharp']);

        expect(wordsListedBy(new ListWordsQuery(search: 'canny', sort: WordSort::Recent)))
            ->toBe(['canny', 'astute']);
    });

    test('marks whether each search result matched on its spelling', function () {
        /** @var TestCase $this */
        Word::factory()->create(['word' => 'canny', 'short_definition' => 'shrewd']);
        Word::factory()->create(['word' => 'astute', 'short_definition' => 'canny; sharp']);

        $matchesWord = (new ListWordsQuery(search: 'canny'))->paginate(perPage: 50, page: 1)
            ->mapWithKeys(fn (Word $word): array => [$word->word => $word->matches_word])
            ->all();

        expect($matchesWord)->toBe(['canny' => true, 'astute' => false]);
    });

    test('lists the newest first when asked for the best match without a search', function () {
        /** @var TestCase $this */
        $this->travelTo('2026-11-01');
        Word::factory()->create(['word' => 'apricity']);
        $this->travelTo('2026-11-02');
        Word::factory()->create(['word' => 'zephyr']);

        expect(wordsListedBy(new ListWordsQuery(sort: WordSort::BestMatch)))->toBe(['zephyr', 'apricity']);
    });
});

describe('countsByStatus()', function () {
    test('counts every status, including the ones no word has', function () {
        /** @var TestCase $this */
        Word::factory()->count(2)->create();
        Word::factory()->mastered()->create();

        expect((new ListWordsQuery)->countsByStatus())->toBe([
            'new' => 2,
            'mastered' => 1,
            'troublesome' => 0,
            'learning' => 0,
        ]);
    });

    test('counts only the words the search finds', function () {
        /** @var TestCase $this */
        Word::factory()->mastered()->create(['word' => 'ephemeral']);
        Word::factory()->troublesome()->create(['word' => 'evanescent', 'short_definition' => 'ephemeral; fading']);
        Word::factory()->troublesome()->create(['word' => 'liminal']);

        expect((new ListWordsQuery(search: 'ephemeral'))->countsByStatus())->toBe([
            'new' => 0,
            'mastered' => 1,
            'troublesome' => 1,
            'learning' => 0,
        ]);
    });

    test('counts every status even when the list keeps only one', function () {
        /** @var TestCase $this */
        Word::factory()->create();
        Word::factory()->learning()->create();

        $query = new ListWordsQuery(status: LearningStatus::Learning);

        expect($query->countsByStatus())->toMatchArray(['new' => 1, 'learning' => 1])
            ->and($query->paginate(perPage: 10, page: 1)->total())->toBe(1);
    });
});
