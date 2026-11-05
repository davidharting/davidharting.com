<?php

/*
 * Most expectations here are ported from py-fsrs v6.3.2's tests/test_basic.py,
 * the reference this scheduler is ported from, so a failure means the PHP
 * port has drifted from FSRS-6 as py-fsrs implements it.
 */

use App\Fsrs\CardSchedule;
use App\Fsrs\CardState;
use App\Fsrs\Rating;
use App\Fsrs\Scheduler;
use Random\Engine\Mt19937;
use Random\Randomizer;

function unreviewedSchedule(): CardSchedule
{
    return CardSchedule::unreviewed(new DateTimeImmutable('2022-11-29 12:30:00', new DateTimeZone('UTC')));
}

/**
 * Review a card with each rating in turn, each time as soon as it falls due.
 */
function reviewScheduleOnTime(Scheduler $scheduler, CardSchedule $card, Rating ...$ratings): CardSchedule
{
    foreach ($ratings as $rating) {
        $card = $scheduler->review($card, $rating, $card->due);
    }

    return $card;
}

function scheduleMinutesUntilDue(CardSchedule $card): float
{
    return ($card->due->getTimestamp() - $card->lastReview->getTimestamp()) / 60;
}

function scheduleDaysUntilDue(CardSchedule $card): int
{
    return intdiv($card->due->getTimestamp() - $card->lastReview->getTimestamp(), 86400);
}

describe('review()', function () {
    test('spaces a run of on-time reviews exactly as py-fsrs does', function () {
        $scheduler = new Scheduler(enableFuzzing: false);
        $card = unreviewedSchedule();
        $ratings = [
            Rating::Good, Rating::Good, Rating::Good, Rating::Good, Rating::Good, Rating::Good,
            Rating::Again, Rating::Again,
            Rating::Good, Rating::Good, Rating::Good, Rating::Good, Rating::Good,
        ];

        $intervals = [];
        foreach ($ratings as $rating) {
            $card = $scheduler->review($card, $rating, $card->due);
            $intervals[] = scheduleDaysUntilDue($card);
        }

        expect($intervals)->toBe([0, 2, 11, 46, 163, 498, 0, 0, 2, 4, 7, 12, 21]);
    });

    test('reaches the same stability and difficulty as py-fsrs after a mixed run', function () {
        $scheduler = new Scheduler;
        $card = unreviewedSchedule();
        $reviewedAt = $card->due;
        $reviews = [
            [Rating::Again, 0], [Rating::Good, 0], [Rating::Good, 1],
            [Rating::Good, 3], [Rating::Good, 8], [Rating::Good, 21],
        ];

        foreach ($reviews as [$rating, $daysLater]) {
            $reviewedAt = $reviewedAt->modify("+{$daysLater} days");
            $card = $scheduler->review($card, $rating, $reviewedAt);
        }

        expect($card->stability)->toEqualWithDelta(53.62691, 1e-4)
            ->and($card->difficulty)->toEqualWithDelta(6.3574867, 1e-4);
    });

    test('bottoms difficulty out at 1 after repeated Easy reviews', function () {
        $scheduler = new Scheduler(enableFuzzing: false);
        $card = unreviewedSchedule();

        // As in py-fsrs's test: ten reviews a microsecond apart, all the same day.
        for ($i = 0; $i < 10; $i++) {
            $card = $scheduler->review($card, Rating::Easy, $card->lastReview?->modify('+1 microsecond') ?? $card->due);
        }

        expect($card->difficulty)->toBe(1.0);
    });

    test('never lets stability fall below the minimum, however often a card is forgotten', function () {
        $scheduler = new Scheduler;
        $card = unreviewedSchedule();

        $lowestStability = INF;
        for ($i = 0; $i < 100; $i++) {
            $card = $scheduler->review($card, Rating::Again, $card->due->modify('+1 day'));
            $lowestStability = min($lowestStability, $card->stability);
        }

        expect($lowestStability)->toBe(Scheduler::STABILITY_MIN);
    });

    test('does not lower stability when a card is rated Hard again the same day', function () {
        $scheduler = new Scheduler(learningSteps: []);
        $reviewedAt = new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC'));
        $card = $scheduler->review(unreviewedSchedule(), Rating::Good, $reviewedAt);

        $reviewedAgain = $scheduler->review($card, Rating::Hard, $reviewedAt->modify('+1 minute'));

        expect($reviewedAgain->stability)->toBeGreaterThanOrEqual($card->stability);
    });

    test('records the review time in UTC whatever time zone it arrives in', function () {
        $scheduler = new Scheduler;

        $card = $scheduler->review(unreviewedSchedule(), Rating::Good, new DateTimeImmutable('2026-01-01 09:00', new DateTimeZone('America/New_York')));

        expect($card->lastReview->format(DATE_ATOM))->toBe('2026-01-01T14:00:00+00:00')
            ->and($card->due->getTimezone()->getName())->toBe('UTC');
    });

    test('never schedules a card further ahead than the maximum interval', function () {
        $scheduler = new Scheduler(maximumInterval: 100);
        $card = unreviewedSchedule();

        foreach ([Rating::Easy, Rating::Good, Rating::Easy, Rating::Good] as $rating) {
            $card = $scheduler->review($card, $rating, $card->due);
            expect(scheduleDaysUntilDue($card))->toBeLessThanOrEqual(100);
        }
    });

    test('fuzzes review intervals to a random nearby length', function () {
        $intervals = collect(range(1, 50))->map(function (int $seed) {
            $scheduler = new Scheduler(randomizer: new Randomizer(new Mt19937($seed)));
            $card = reviewScheduleOnTime($scheduler, unreviewedSchedule(), Rating::Good, Rating::Good, Rating::Good);

            return scheduleDaysUntilDue($card);
        });

        // Unfuzzed, this review is 11 days out (see the py-fsrs run above).
        expect($intervals->min())->toBeGreaterThanOrEqual(9)
            ->and($intervals->max())->toBeLessThanOrEqual(14)
            ->and($intervals->unique()->count())->toBeGreaterThan(1);
    });
});

describe('review() of a card being learned', function () {
    test('moves to the second learning step, 10 minutes out, on Good', function () {
        $card = reviewScheduleOnTime(new Scheduler, unreviewedSchedule(), Rating::Good);

        expect($card->state)->toBe(CardState::Learning)
            ->and($card->step)->toBe(1)
            ->and(scheduleMinutesUntilDue($card))->toEqual(10);
    });

    test('graduates to review, 2 days out, on Good at the last learning step', function () {
        $card = reviewScheduleOnTime(new Scheduler, unreviewedSchedule(), Rating::Good, Rating::Good);

        expect($card->state)->toBe(CardState::Review)
            ->and($card->step)->toBeNull()
            ->and(scheduleDaysUntilDue($card))->toBe(2);
    });

    test('restarts at the first learning step, 1 minute out, on Again', function () {
        $card = reviewScheduleOnTime(new Scheduler, unreviewedSchedule(), Rating::Again);

        expect($card->state)->toBe(CardState::Learning)
            ->and($card->step)->toBe(0)
            ->and(scheduleMinutesUntilDue($card))->toEqual(1);
    });

    test('stays on the first step, halfway between the first two steps, on Hard', function () {
        $card = reviewScheduleOnTime(new Scheduler, unreviewedSchedule(), Rating::Hard);

        expect($card->state)->toBe(CardState::Learning)
            ->and($card->step)->toBe(0)
            ->and(scheduleMinutesUntilDue($card))->toEqual(5.5);
    });

    test('waits one and a half steps on Hard when there is only one learning step', function () {
        $card = reviewScheduleOnTime(new Scheduler(learningSteps: [600]), unreviewedSchedule(), Rating::Hard);

        expect($card->state)->toBe(CardState::Learning)
            ->and(scheduleMinutesUntilDue($card))->toEqual(15);
    });

    test('repeats the current step on Hard after the first step', function () {
        $card = reviewScheduleOnTime(new Scheduler, unreviewedSchedule(), Rating::Good, Rating::Hard);

        expect($card->step)->toBe(1)
            ->and(scheduleMinutesUntilDue($card))->toEqual(10);
    });

    test('graduates straight to review, 8 days out, on Easy', function () {
        $card = reviewScheduleOnTime(new Scheduler(enableFuzzing: false), unreviewedSchedule(), Rating::Easy);

        expect($card->state)->toBe(CardState::Review)
            ->and($card->step)->toBeNull()
            ->and(scheduleDaysUntilDue($card))->toBe(8);
    });

    test('goes straight to review, a day out, even on Again, when there are no learning steps', function () {
        $card = reviewScheduleOnTime(new Scheduler(learningSteps: []), unreviewedSchedule(), Rating::Again);

        expect($card->state)->toBe(CardState::Review)
            ->and(scheduleDaysUntilDue($card))->toBe(1);
    });

    test('graduates a card left on a step this scheduler does not have', function () {
        $card = reviewScheduleOnTime(new Scheduler(learningSteps: [60, 600]), unreviewedSchedule(), Rating::Good);

        $card = reviewScheduleOnTime(new Scheduler(learningSteps: [60]), $card, Rating::Hard);

        expect($card->state)->toBe(CardState::Review)
            ->and($card->step)->toBeNull();
    });
});

describe('review() of a learned card', function () {
    test('stretches stability and the next gap least on Hard and most on Easy', function () {
        $scheduler = new Scheduler(enableFuzzing: false);
        $learned = reviewScheduleOnTime($scheduler, unreviewedSchedule(), Rating::Good, Rating::Good);

        [$hard, $good, $easy] = array_map(
            fn (Rating $rating) => reviewScheduleOnTime($scheduler, $learned, $rating),
            [Rating::Hard, Rating::Good, Rating::Easy],
        );

        expect($hard->stability)->toBeGreaterThan($learned->stability)->toBeLessThan($good->stability)
            ->and($easy->stability)->toBeGreaterThan($good->stability)
            ->and(scheduleDaysUntilDue($hard))->toBeLessThan(scheduleDaysUntilDue($good))
            ->and(scheduleDaysUntilDue($easy))->toBeGreaterThan(scheduleDaysUntilDue($good))
            ->and($hard->difficulty)->toBeGreaterThan($good->difficulty)
            ->and($easy->difficulty)->toBeLessThan($good->difficulty);
    });

    test('sends it to relearning, 10 minutes out, on Again', function () {
        $scheduler = new Scheduler(enableFuzzing: false);

        $card = reviewScheduleOnTime($scheduler, unreviewedSchedule(), Rating::Good, Rating::Good, Rating::Good, Rating::Again);

        expect($card->state)->toBe(CardState::Relearning)
            ->and($card->step)->toBe(0)
            ->and(scheduleMinutesUntilDue($card))->toEqual(10);
    });

    test('keeps it in review on Again when there are no relearning steps', function () {
        $card = reviewScheduleOnTime(new Scheduler(relearningSteps: []), unreviewedSchedule(), Rating::Good, Rating::Good, Rating::Again);

        expect($card->state)->toBe(CardState::Review)
            ->and(scheduleDaysUntilDue($card))->toBeGreaterThanOrEqual(1);
    });
});

describe('review() of a forgotten card', function () {
    test('repeats the relearning step on Again, then returns it to review on Good', function () {
        $scheduler = new Scheduler(enableFuzzing: false);
        $forgotten = reviewScheduleOnTime($scheduler, unreviewedSchedule(), Rating::Good, Rating::Good, Rating::Good, Rating::Again);

        $againAgain = reviewScheduleOnTime($scheduler, $forgotten, Rating::Again);
        $relearned = reviewScheduleOnTime($scheduler, $againAgain, Rating::Good);

        expect($againAgain->state)->toBe(CardState::Relearning)
            ->and($againAgain->step)->toBe(0)
            ->and(scheduleMinutesUntilDue($againAgain))->toEqual(10)
            ->and($relearned->state)->toBe(CardState::Review)
            ->and($relearned->step)->toBeNull()
            ->and(scheduleDaysUntilDue($relearned))->toBeGreaterThanOrEqual(1);
    });

    test('waits one and a half steps on Hard when there is only one relearning step', function () {
        $scheduler = new Scheduler(relearningSteps: [600]);
        $forgotten = reviewScheduleOnTime($scheduler, unreviewedSchedule(), Rating::Easy, Rating::Again);

        $card = reviewScheduleOnTime($scheduler, $forgotten, Rating::Hard);

        expect($card->state)->toBe(CardState::Relearning)
            ->and($card->step)->toBe(0)
            ->and(scheduleMinutesUntilDue($card))->toEqual(15);
    });

    test('returns it to review when it is relearned a day late', function () {
        $scheduler = new Scheduler;
        $forgotten = reviewScheduleOnTime($scheduler, unreviewedSchedule(), Rating::Easy, Rating::Again);

        $card = $scheduler->review($forgotten, Rating::Good, $forgotten->due->modify('+1 day'));

        expect($card->state)->toBe(CardState::Review);
    });
});

describe('retrievability()', function () {
    test('is zero for a card that has never been reviewed', function () {
        $schedule = unreviewedSchedule();

        expect((new Scheduler)->retrievability($schedule, $schedule->due->modify('+1 day')))->toBe(0.0);
    });

    test("is 90% once as many days have passed as the card's stability", function () {
        $reviewedAt = new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC'));
        $schedule = new CardSchedule(CardState::Review, null, 10.0, 5.0, $reviewedAt->modify('+10 days'), $reviewedAt);

        expect((new Scheduler)->retrievability($schedule, $reviewedAt->modify('+10 days')))->toEqualWithDelta(0.9, 1e-12);
    });

    test('starts at one and falls as days pass without a review', function () {
        $scheduler = new Scheduler(enableFuzzing: false);
        $card = reviewScheduleOnTime($scheduler, unreviewedSchedule(), Rating::Good, Rating::Good);

        $justReviewed = $scheduler->retrievability($card, $card->lastReview);
        $aWeekLater = $scheduler->retrievability($card, $card->lastReview->modify('+1 week'));
        $aYearLater = $scheduler->retrievability($card, $card->lastReview->modify('+1 year'));

        expect($justReviewed)->toEqual(1.0)
            ->and($aWeekLater)->toBeLessThan($justReviewed)->toBeGreaterThan($aYearLater)
            ->and($aYearLater)->toBeGreaterThan(0.0);
    });
});

describe('__construct()', function () {
    test('refuses a parameter outside its bounds', function () {
        $parameters = Scheduler::DEFAULT_PARAMETERS;
        $parameters[6] = 100;

        new Scheduler(parameters: $parameters);
    })->throws(InvalidArgumentException::class, 'parameters[6] = 100 is out of bounds');

    test('refuses the wrong number of parameters', function () {
        new Scheduler(parameters: array_slice(Scheduler::DEFAULT_PARAMETERS, 0, 20));
    })->throws(InvalidArgumentException::class, 'Expected 21 FSRS parameters, got 20.');

    test('refuses a parameter that is not a number', function () {
        $parameters = Scheduler::DEFAULT_PARAMETERS;
        $parameters[8] = NAN;

        new Scheduler(parameters: $parameters);
    })->throws(InvalidArgumentException::class, 'parameters[8] = NAN is out of bounds');

    test('refuses a desired retention that is not a fraction', function (float $retention) {
        new Scheduler(desiredRetention: $retention);
    })->with([0.0, 1.0, 90.0])->throws(InvalidArgumentException::class, 'Desired retention must be between 0 and 1');

    test('refuses a learning step shorter than a second', function () {
        new Scheduler(learningSteps: [60, 0]);
    })->throws(InvalidArgumentException::class, 'Learning steps must be whole numbers of seconds');

    test('refuses a relearning step shorter than a second', function () {
        new Scheduler(relearningSteps: [0]);
    })->throws(InvalidArgumentException::class, 'Relearning steps must be whole numbers of seconds');

    test('refuses a maximum interval shorter than a day', function () {
        new Scheduler(maximumInterval: 0);
    })->throws(InvalidArgumentException::class, 'The maximum interval must be at least 1 day');
});
