<?php

use App\Enum\LearningStatus;
use App\Models\Flashcard;
use App\Models\Word;
use Tests\TestCase;

/**
 * The status the SQL rule gives a flashcard, read back from the database.
 */
function learningStatusInSql(Flashcard $flashcard): LearningStatus
{
    return LearningStatus::from(
        Flashcard::query()->whereKey($flashcard->id)->selectRaw(LearningStatus::sql().' as status')->value('status'),
    );
}

test('a word that has never been practised is new', function () {
    /** @var TestCase $this */
    $flashcard = Word::factory()->create()->flashcard;

    expect($flashcard->learningStatus())->toBe(LearningStatus::New)
        ->and(learningStatusInSql($flashcard))->toBe(LearningStatus::New);
});

test('a practised word gets the first status that fits, the same in PHP and SQL', function (float $stability, float $difficulty, LearningStatus $expected) {
    /** @var TestCase $this */
    $flashcard = Word::factory()->practised($stability, $difficulty)->create()->flashcard->refresh();

    expect($flashcard->learningStatus())->toBe($expected)
        ->and(learningStatusInSql($flashcard))->toBe($expected);
})->with([
    'remembered for three weeks: mastered' => [21, 3, LearningStatus::Mastered],
    'remembered for three weeks, even if hard: still mastered' => [21, 9, LearningStatus::Mastered],
    'just short of three weeks and hard: troublesome' => [20.9, 7, LearningStatus::Troublesome],
    'just short of three weeks and not quite hard: learning' => [20.9, 6.9, LearningStatus::Learning],
    'barely started and easy: learning' => [0.5, 1, LearningStatus::Learning],
]);
