<?php

use App\Enum\LearningStatus;
use App\Fsrs\Rating;
use App\Models\Flashcard;
use App\Models\Word;
use Tests\TestCase;

test('a word that has never been practised is new', function () {
    /** @var TestCase $this */
    $word = Word::factory()->create();

    expect($word->flashcard->learning_status)->toBe(LearningStatus::New);
});

test('a practised word gets the first status that fits its schedule', function (float $stability, float $difficulty, LearningStatus $expected) {
    /** @var TestCase $this */
    $word = Word::factory()->practised($stability, $difficulty)->create();

    expect($word->flashcard->learning_status)->toBe($expected)
        ->and(Flashcard::query()->where('learning_status', $expected)->count())->toBe(1);
})->with([
    'remembered for three weeks: mastered' => [21, 3, LearningStatus::Mastered],
    'remembered for three weeks, even if hard: still mastered' => [21, 9, LearningStatus::Mastered],
    'just short of three weeks and hard: troublesome' => [20.9, 7, LearningStatus::Troublesome],
    'just short of three weeks and not quite hard: learning' => [20.9, 6.9, LearningStatus::Learning],
    'barely started and easy: learning' => [0.5, 1, LearningStatus::Learning],
]);

test('grading a flashcard updates its status straight away', function () {
    /** @var TestCase $this */
    $flashcard = Word::factory()->create()->flashcard;

    $flashcard->grade(Rating::Good);

    expect($flashcard->learning_status)->toBe(LearningStatus::Learning);
});
