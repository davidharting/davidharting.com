<?php

use App\Enum\LearningStatus;
use App\Models\Word;
use App\Queries\ListWordsQuery;
use Tests\TestCase;

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
