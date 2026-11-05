<?php

namespace App\Support\Fsrs;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The FSRS memory state of one card: everything the scheduler needs to know
 * about it, and everything it changes when the card is reviewed. Immutable:
 * reviewing a card returns a new schedule.
 */
final readonly class CardSchedule
{
    /**
     * @param  int|null  $step  The current learning or relearning step, or null in the Review state.
     * @param  float|null  $stability  Days until recall probability falls to 90%; null before the first review.
     * @param  float|null  $difficulty  From 1 (easiest) to 10 (hardest); null before the first review.
     * @param  CarbonImmutable  $due  When the card is next due, in UTC.
     * @param  CarbonImmutable|null  $lastReview  When the card was last reviewed, in UTC; null before the first review.
     */
    public function __construct(
        public CardState $state,
        public ?int $step,
        public ?float $stability,
        public ?float $difficulty,
        public CarbonImmutable $due,
        public ?CarbonImmutable $lastReview,
    ) {}

    /**
     * A card that has never been reviewed, due straight away.
     */
    public static function new(DateTimeInterface $due): self
    {
        return new self(
            state: CardState::Learning,
            step: 0,
            stability: null,
            difficulty: null,
            due: CarbonImmutable::instance($due)->utc(),
            lastReview: null,
        );
    }
}
