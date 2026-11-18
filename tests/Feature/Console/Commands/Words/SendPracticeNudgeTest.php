<?php

use App\Fsrs\Rating;
use App\Models\Word;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Testing\FakeNutgram;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->freezeTime();
});

/**
 * The fake Telegram bot the command sends through.
 */
function practiceNudgeBot(): FakeNutgram
{
    return app(Nutgram::class);
}

/**
 * A word just graded Got it, so it isn't due for a while.
 */
function practiceNudgePractisedWord(): Word
{
    $practised = Word::factory()->create();
    $practised->flashcard->grade(Rating::Good);

    return $practised;
}

test('counts only the words that are due, and links to practise them', function () {
    /** @var TestCase $this */
    Word::factory()->count(3)->create();
    practiceNudgePractisedWord();

    $this->artisan('words:send-practice-nudge')->assertSuccessful();

    practiceNudgeBot()->assertReply('sendMessage', [
        'chat_id' => config('nutgram.owner_user_id'),
        'text' => "3 words due\n".route('words.practice'),
    ], index: 0);
});

test('says "word" when only one is due', function () {
    /** @var TestCase $this */
    Word::factory()->create();

    $this->artisan('words:send-practice-nudge')->assertSuccessful();

    practiceNudgeBot()->assertReply('sendMessage', [
        'text' => "1 word due\n".route('words.practice'),
    ], index: 0);
});

test('sends nothing when no words are due', function () {
    /** @var TestCase $this */
    practiceNudgePractisedWord();

    $this->artisan('words:send-practice-nudge')->assertSuccessful();

    practiceNudgeBot()->assertNoReply();
});

test("fails without sending when David's Telegram id is not configured", function () {
    /** @var TestCase $this */
    config(['nutgram.owner_user_id' => null]);
    Word::factory()->create();

    $this->artisan('words:send-practice-nudge')->assertFailed();

    practiceNudgeBot()->assertNoReply();
});
