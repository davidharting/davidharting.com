<?php

use App\Fsrs\CardState;
use App\Fsrs\Rating;
use App\Models\Flashcard;
use App\Models\Review;
use App\Models\Word;
use Illuminate\Support\Carbon;
use Tests\TestCase;

describe('a word', function () {
    test('gets one flashcard, due straight away, when it is recorded', function () {
        /** @var TestCase $this */
        $this->freezeTime();

        $flashcard = Word::factory()->create()->flashcard;

        expect(Flashcard::count())->toBe(1)
            ->and($flashcard->schedule->state)->toBe(CardState::Learning)
            ->and($flashcard->schedule->lastReview)->toBeNull()
            ->and($flashcard->schedule->due->getTimestamp())->toBe(now()->getTimestamp());
    });

    test('is stored as "word" on its flashcard, not as a class name', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create();

        $this->assertDatabaseHas('flashcards', ['quizzable_type' => 'word', 'quizzable_id' => $word->id]);
    });

    test('takes its flashcard and that flashcard\'s reviews with it when deleted', function () {
        /** @var TestCase $this */
        $word = Word::factory()->create();
        $word->flashcard->grade(Rating::Good);

        $word->delete();

        expect(Flashcard::count())->toBe(0)
            ->and(Review::count())->toBe(0);
    });
});

describe('grade()', function () {
    test('reschedules the flashcard and keeps the new schedule through a reload', function () {
        /** @var TestCase $this */
        $flashcard = Word::factory()->create()->flashcard;
        $reviewedAt = Carbon::parse('2026-01-01 09:00:00.123456', 'UTC');
        $flashcard->schedule; // Read before grading, as a practice screen would.

        $flashcard->grade(Rating::Good, $reviewedAt);
        $reloaded = $flashcard->fresh()->schedule;

        expect($reloaded)->toEqual($flashcard->schedule)
            ->and($reloaded->step)->toBe(1)
            ->and($reloaded->stability)->toBe(2.3065)
            ->and($reloaded->lastReview->format('Y-m-d H:i:s.u'))->toBe('2026-01-01 09:00:00.123456')
            ->and($reloaded->due->format('Y-m-d H:i:s.u'))->toBe('2026-01-01 09:10:00.123456');
    });

    test('logs the review with the rating and the schedule either side', function () {
        /** @var TestCase $this */
        $flashcard = Word::factory()->create()->flashcard;

        $review = $flashcard->grade(Rating::Again, reviewDurationMs: 4200);

        expect($review->fresh())
            ->rating->toBe(Rating::Again)
            ->review_duration_ms->toBe(4200)
            ->schedule_before->stability->toBeNull()
            ->schedule_after->stability->toBe($flashcard->schedule->stability);
    });

    test('logs the review at the exact moment it was graded', function () {
        /** @var TestCase $this */
        $flashcard = Word::factory()->create()->flashcard;

        $review = $flashcard->grade(Rating::Good, Carbon::parse('2026-01-01 09:00:00.123456', 'America/New_York'));

        expect($review->fresh()->reviewed_at->format('Y-m-d H:i:s.u P'))->toBe('2026-01-01 14:00:00.123456 +00:00')
            ->and($flashcard->schedule->lastReview->format('Y-m-d H:i:s.u'))->toBe('2026-01-01 14:00:00.123456');
    });

    test('builds each review on the schedule the previous one left', function () {
        /** @var TestCase $this */
        $flashcard = Word::factory()->create()->flashcard;

        $first = $flashcard->grade(Rating::Good);
        $second = $flashcard->grade(Rating::Good, $flashcard->schedule->due);

        expect($second->schedule_before)->toBe($first->schedule_after)
            ->and($flashcard->schedule->state)->toBe(CardState::Review)
            ->and($flashcard->reviews()->count())->toBe(2);
    });
});

describe('dueAt()', function () {
    test('lists flashcards due by the moment, most overdue first', function () {
        /** @var TestCase $this */
        $this->travelTo('2026-01-01 09:00');
        $older = Word::factory()->create()->flashcard;
        $this->travelTo('2026-01-01 10:00');
        $newer = Word::factory()->create()->flashcard;
        $notYetDue = Word::factory()->create()->flashcard;
        $notYetDue->grade(Rating::Good);

        $due = Flashcard::query()->dueAt(now())->get();

        expect($due->modelKeys())->toBe([$older->id, $newer->id]);
    });

    test('includes a flashcard due this very instant, whatever time zone the moment is in', function () {
        /** @var TestCase $this */
        $this->freezeTime();
        Word::factory()->create();

        expect(Flashcard::query()->dueAt(now())->count())->toBe(1)
            ->and(Flashcard::query()->dueAt(now()->setTimezone('America/New_York'))->count())->toBe(1);
    });
});

test('a review cannot be changed once written', function () {
    /** @var TestCase $this */
    $review = Word::factory()->create()->flashcard->grade(Rating::Good);

    $review->update(['rating' => Rating::Easy]);
})->throws(LogicException::class, 'append-only');
