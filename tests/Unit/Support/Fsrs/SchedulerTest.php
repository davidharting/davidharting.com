<?php

/*
 * Most expectations here are ported from py-fsrs v6.3.2's tests/test_basic.py,
 * the reference this scheduler is ported from, so a failure means the PHP
 * port has drifted from FSRS-6 as py-fsrs implements it.
 */

use App\Support\Fsrs\CardSchedule;
use App\Support\Fsrs\CardState;
use App\Support\Fsrs\Rating;
use App\Support\Fsrs\Scheduler;
use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

function newCard(): CardSchedule
{
    return CardSchedule::new(CarbonImmutable::parse('2022-11-29 12:30:00', 'UTC'));
}

/**
 * Review a card with each rating in turn, each time as soon as it falls due.
 */
function reviewOnTime(Scheduler $scheduler, CardSchedule $card, Rating ...$ratings): CardSchedule
{
    foreach ($ratings as $rating) {
        $card = $scheduler->review($card, $rating, $card->due);
    }

    return $card;
}

function minutesUntilDue(CardSchedule $card): float
{
    return $card->lastReview->diffInMinutes($card->due);
}

function daysUntilDue(CardSchedule $card): int
{
    return (int) floor($card->lastReview->diffInDays($card->due));
}

describe('review()', function () {
    test('spaces a run of on-time reviews exactly as py-fsrs does', function () {
        $scheduler = new Scheduler(enableFuzzing: false);
        $card = newCard();
        $ratings = [
            Rating::Good, Rating::Good, Rating::Good, Rating::Good, Rating::Good, Rating::Good,
            Rating::Again, Rating::Again,
            Rating::Good, Rating::Good, Rating::Good, Rating::Good, Rating::Good,
        ];

        $intervals = [];
        foreach ($ratings as $rating) {
            $card = $scheduler->review($card, $rating, $card->due);
            $intervals[] = daysUntilDue($card);
        }

        expect($intervals)->toBe([0, 2, 11, 46, 163, 498, 0, 0, 2, 4, 7, 12, 21]);
    });

    test('reaches the same stability and difficulty as py-fsrs after a mixed run', function () {
        $scheduler = new Scheduler;
        $card = newCard();
        $reviewedAt = $card->due;
        $reviews = [
            [Rating::Again, 0], [Rating::Good, 0], [Rating::Good, 1],
            [Rating::Good, 3], [Rating::Good, 8], [Rating::Good, 21],
        ];

        foreach ($reviews as [$rating, $daysLater]) {
            $reviewedAt = $reviewedAt->addDays($daysLater);
            $card = $scheduler->review($card, $rating, $reviewedAt);
        }

        expect($card->stability)->toEqualWithDelta(53.62691, 1e-4)
            ->and($card->difficulty)->toEqualWithDelta(6.3574867, 1e-4);
    });

    test('bottoms difficulty out at 1 after repeated Easy reviews', function () {
        $scheduler = new Scheduler(enableFuzzing: false);
        $card = newCard();

        for ($i = 0; $i < 10; $i++) {
            $card = $scheduler->review($card, Rating::Easy, $card->due->addMicroseconds($i));
        }

        expect($card->difficulty)->toBe(1.0);
    });

    test('never lets stability fall below the minimum, however often a card is forgotten', function () {
        $scheduler = new Scheduler;
        $card = newCard();

        for ($i = 0; $i < 1000; $i++) {
            $card = $scheduler->review($card, Rating::Again, $card->due->addDay());
            expect($card->stability)->toBeGreaterThanOrEqual(Scheduler::STABILITY_MIN);
        }
    });

    test('does not lower stability when a card is rated Hard again the same day', function () {
        $scheduler = new Scheduler(learningSteps: []);
        $reviewedAt = CarbonImmutable::parse('2026-01-01', 'UTC');
        $card = $scheduler->review(newCard(), Rating::Good, $reviewedAt);

        $reviewedAgain = $scheduler->review($card, Rating::Hard, $reviewedAt->addMinute());

        expect($reviewedAgain->stability)->toBe($card->stability);
    });

    test('records the review time in UTC whatever time zone it arrives in', function () {
        $scheduler = new Scheduler;

        $card = $scheduler->review(newCard(), Rating::Good, CarbonImmutable::parse('2026-01-01 09:00', 'America/New_York'));

        expect($card->lastReview->toIso8601String())->toBe('2026-01-01T14:00:00+00:00')
            ->and($card->due->tzName)->toBe('UTC');
    });

    test('never schedules a card further ahead than the maximum interval', function () {
        $scheduler = new Scheduler(maximumInterval: 100);
        $card = newCard();

        foreach ([Rating::Easy, Rating::Good, Rating::Easy, Rating::Good] as $rating) {
            $card = $scheduler->review($card, $rating, $card->due);
            expect(daysUntilDue($card))->toBeLessThanOrEqual(100);
        }
    });

    test('fuzzes review intervals to a random nearby length', function () {
        $intervals = collect(range(1, 50))->map(function (int $seed) {
            $scheduler = new Scheduler(randomizer: new Randomizer(new Mt19937($seed)));
            $card = reviewOnTime($scheduler, newCard(), Rating::Good, Rating::Good, Rating::Good);

            return daysUntilDue($card);
        });

        // Unfuzzed, this review is 11 days out (see the py-fsrs run above).
        expect($intervals->min())->toBeGreaterThanOrEqual(9)
            ->and($intervals->max())->toBeLessThanOrEqual(14)
            ->and($intervals->unique()->count())->toBeGreaterThan(1);
    });
});

describe('review() of a card being learned', function () {
    test('moves to the second learning step, 10 minutes out, on Good', function () {
        $card = reviewOnTime(new Scheduler, newCard(), Rating::Good);

        expect($card->state)->toBe(CardState::Learning)
            ->and($card->step)->toBe(1)
            ->and(minutesUntilDue($card))->toEqual(10);
    });

    test('graduates to review, at least a day out, on Good at the last learning step', function () {
        $card = reviewOnTime(new Scheduler, newCard(), Rating::Good, Rating::Good);

        expect($card->state)->toBe(CardState::Review)
            ->and($card->step)->toBeNull()
            ->and(daysUntilDue($card))->toBeGreaterThanOrEqual(1);
    });

    test('restarts at the first learning step, 1 minute out, on Again', function () {
        $card = reviewOnTime(new Scheduler, newCard(), Rating::Again);

        expect($card->state)->toBe(CardState::Learning)
            ->and($card->step)->toBe(0)
            ->and(minutesUntilDue($card))->toEqual(1);
    });

    test('stays on the first step, halfway between the first two steps, on Hard', function () {
        $card = reviewOnTime(new Scheduler, newCard(), Rating::Hard);

        expect($card->state)->toBe(CardState::Learning)
            ->and($card->step)->toBe(0)
            ->and(minutesUntilDue($card))->toEqual(5.5);
    });

    test('waits one and a half steps on Hard when there is only one learning step', function () {
        $card = reviewOnTime(new Scheduler(learningSteps: [600]), newCard(), Rating::Hard);

        expect($card->state)->toBe(CardState::Learning)
            ->and(minutesUntilDue($card))->toEqual(15);
    });

    test('repeats the current step on Hard after the first step', function () {
        $card = reviewOnTime(new Scheduler, newCard(), Rating::Good, Rating::Hard);

        expect($card->step)->toBe(1)
            ->and(minutesUntilDue($card))->toEqual(10);
    });

    test('graduates straight to review, at least a day out, on Easy', function () {
        $card = reviewOnTime(new Scheduler, newCard(), Rating::Easy);

        expect($card->state)->toBe(CardState::Review)
            ->and($card->step)->toBeNull()
            ->and(daysUntilDue($card))->toBeGreaterThanOrEqual(1);
    });

    test('goes straight to review, even on Again, when there are no learning steps', function () {
        $card = reviewOnTime(new Scheduler(learningSteps: []), newCard(), Rating::Again);

        expect($card->state)->toBe(CardState::Review)
            ->and(daysUntilDue($card))->toBeGreaterThanOrEqual(1);
    });

    test('graduates a card left on a step this scheduler does not have', function () {
        $card = reviewOnTime(new Scheduler(learningSteps: [60, 600]), newCard(), Rating::Good);

        $card = reviewOnTime(new Scheduler(learningSteps: [60]), $card, Rating::Hard);

        expect($card->state)->toBe(CardState::Review)
            ->and($card->step)->toBeNull();
    });
});

describe('review() of a learned card', function () {
    test('schedules it at least a day further out on Good', function () {
        $scheduler = new Scheduler(enableFuzzing: false);
        $learned = reviewOnTime($scheduler, newCard(), Rating::Good, Rating::Good);

        $card = reviewOnTime($scheduler, $learned, Rating::Good);

        expect($card->state)->toBe(CardState::Review)
            ->and(daysUntilDue($card))->toBeGreaterThanOrEqual(1);
    });

    test('sends it to relearning, 10 minutes out, on Again', function () {
        $scheduler = new Scheduler(enableFuzzing: false);

        $card = reviewOnTime($scheduler, newCard(), Rating::Good, Rating::Good, Rating::Good, Rating::Again);

        expect($card->state)->toBe(CardState::Relearning)
            ->and($card->step)->toBe(0)
            ->and(minutesUntilDue($card))->toEqual(10);
    });

    test('keeps it in review on Again when there are no relearning steps', function () {
        $card = reviewOnTime(new Scheduler(relearningSteps: []), newCard(), Rating::Good, Rating::Good, Rating::Again);

        expect($card->state)->toBe(CardState::Review)
            ->and(daysUntilDue($card))->toBeGreaterThanOrEqual(1);
    });
});

describe('review() of a forgotten card', function () {
    test('repeats the relearning step on Again, then returns it to review on Good', function () {
        $scheduler = new Scheduler(enableFuzzing: false);
        $forgotten = reviewOnTime($scheduler, newCard(), Rating::Good, Rating::Good, Rating::Good, Rating::Again);

        $againAgain = reviewOnTime($scheduler, $forgotten, Rating::Again);
        $relearned = reviewOnTime($scheduler, $againAgain, Rating::Good);

        expect($againAgain->state)->toBe(CardState::Relearning)
            ->and($againAgain->step)->toBe(0)
            ->and(minutesUntilDue($againAgain))->toEqual(10)
            ->and($relearned->state)->toBe(CardState::Review)
            ->and($relearned->step)->toBeNull()
            ->and(daysUntilDue($relearned))->toBeGreaterThanOrEqual(1);
    });

    test('waits one and a half steps on Hard when there is only one relearning step', function () {
        $scheduler = new Scheduler(relearningSteps: [600]);
        $forgotten = reviewOnTime($scheduler, newCard(), Rating::Easy, Rating::Again);

        $card = reviewOnTime($scheduler, $forgotten, Rating::Hard);

        expect($card->state)->toBe(CardState::Relearning)
            ->and($card->step)->toBe(0)
            ->and(minutesUntilDue($card))->toEqual(15);
    });

    test('returns it to review when it is relearned a day late', function () {
        $scheduler = new Scheduler;
        $forgotten = reviewOnTime($scheduler, newCard(), Rating::Easy, Rating::Again);

        $card = $scheduler->review($forgotten, Rating::Good, $forgotten->due->addDay());

        expect($card->state)->toBe(CardState::Review);
    });
});

describe('retrievability()', function () {
    test('is zero for a card that has never been reviewed', function () {
        expect((new Scheduler)->retrievability(newCard(), now()))->toBe(0.0);
    });

    test('starts at one and falls as days pass without a review', function () {
        $scheduler = new Scheduler(enableFuzzing: false);
        $card = reviewOnTime($scheduler, newCard(), Rating::Good, Rating::Good);

        $justReviewed = $scheduler->retrievability($card, $card->lastReview);
        $aWeekLater = $scheduler->retrievability($card, $card->lastReview->addWeek());
        $aYearLater = $scheduler->retrievability($card, $card->lastReview->addYear());

        expect($justReviewed)->toEqual(1.0)
            ->and($aWeekLater)->toBeLessThan($justReviewed)->toBeGreaterThan($aYearLater)
            ->and($aYearLater)->toBeGreaterThan(0.0);
    });
});

describe('__construct()', function () {
    test('accepts the default FSRS-6 parameters', function () {
        expect((new Scheduler)->parameters)->toBe(Scheduler::DEFAULT_PARAMETERS);
    });

    test('refuses a parameter outside its bounds', function () {
        $parameters = Scheduler::DEFAULT_PARAMETERS;
        $parameters[6] = 100;

        new Scheduler(parameters: $parameters);
    })->throws(InvalidArgumentException::class, 'parameters[6] = 100 is out of bounds');

    test('refuses the wrong number of parameters', function () {
        new Scheduler(parameters: array_slice(Scheduler::DEFAULT_PARAMETERS, 0, 20));
    })->throws(InvalidArgumentException::class, 'Expected 21 FSRS parameters, got 20.');
});
