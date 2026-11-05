<?php

use App\Fsrs\CardSchedule;
use App\Fsrs\CardState;

describe('__construct()', function () {
    test('keeps its moments in UTC whatever time zone they arrive in', function () {
        $eastern = new DateTimeImmutable('2026-01-01 09:00', new DateTimeZone('America/New_York'));

        $schedule = new CardSchedule(CardState::Review, null, 3.0, 5.0, $eastern->modify('+3 days'), $eastern);

        expect($schedule->lastReview->format(DATE_ATOM))->toBe('2026-01-01T14:00:00+00:00')
            ->and($schedule->due->getTimezone()->getName())->toBe('UTC');
    });

    test('refuses combinations FSRS never produces', function (CardState $state, ?int $step, ?float $stability, ?float $difficulty, bool $reviewed) {
        $due = new DateTimeImmutable('2026-01-02', new DateTimeZone('UTC'));

        new CardSchedule($state, $step, $stability, $difficulty, $due, $reviewed ? $due->modify('-1 day') : null);
    })->with([
        'a learned card on a step' => [CardState::Review, 0, 3.0, 5.0, true],
        'a learning card with no step' => [CardState::Learning, null, 3.0, 5.0, true],
        'stability without difficulty' => [CardState::Learning, 0, 3.0, null, true],
        'stability but never reviewed' => [CardState::Learning, 0, 3.0, 5.0, false],
        'reviewed but no stability' => [CardState::Learning, 0, null, null, true],
        'never reviewed but relearning' => [CardState::Relearning, 0, null, null, false],
    ])->throws(InvalidArgumentException::class);
});

describe('unreviewed()', function () {
    test('is learning at the first step, due straight away', function () {
        $due = new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC'));

        $schedule = CardSchedule::unreviewed($due);

        expect($schedule->state)->toBe(CardState::Learning)
            ->and($schedule->step)->toBe(0)
            ->and($schedule->stability)->toBeNull()
            ->and($schedule->due)->toEqual($due);
    });
});
