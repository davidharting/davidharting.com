<?php

namespace App\Fsrs;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * The FSRS memory state of one card: everything the scheduler needs to know
 * about it, and everything it changes when the card is reviewed. Immutable:
 * reviewing a card returns a new schedule. Refuses combinations FSRS never
 * produces, so a corrupt stored card fails loudly instead of being scheduled.
 */
final readonly class CardSchedule
{
    /** When the card is next due, in UTC. */
    public DateTimeImmutable $due;

    /** When the card was last reviewed, in UTC; null before the first review. */
    public ?DateTimeImmutable $lastReview;

    /**
     * @param  int|null  $step  The current learning or relearning step, or null in the Review state.
     * @param  float|null  $stability  Days until recall probability falls to 90%; null before the first review.
     * @param  float|null  $difficulty  From 1 (easiest) to 10 (hardest); null before the first review.
     */
    public function __construct(
        public CardState $state,
        public ?int $step,
        public ?float $stability,
        public ?float $difficulty,
        DateTimeInterface $due,
        ?DateTimeInterface $lastReview,
    ) {
        if (($state === CardState::Review) !== ($step === null)) {
            throw new InvalidArgumentException("A card in the {$state->name} state ".($step === null ? 'needs a step.' : 'has no step.'));
        }

        if (($stability === null) !== ($difficulty === null)) {
            throw new InvalidArgumentException('A card has both stability and difficulty, or neither.');
        }

        if (($stability === null) !== ($lastReview === null)) {
            throw new InvalidArgumentException('A card has stability and difficulty exactly when it has been reviewed.');
        }

        if ($stability === null && $state !== CardState::Learning) {
            throw new InvalidArgumentException('A card that has never been reviewed is in the Learning state.');
        }

        $this->due = self::inUtc($due);
        $this->lastReview = $lastReview === null ? null : self::inUtc($lastReview);
    }

    /**
     * A card that has never been reviewed, due straight away.
     */
    public static function unreviewed(DateTimeInterface $due): self
    {
        return new self(
            state: CardState::Learning,
            step: 0,
            stability: null,
            difficulty: null,
            due: $due,
            lastReview: null,
        );
    }

    /**
     * The same moment as an immutable date in UTC.
     */
    public static function inUtc(DateTimeInterface $moment): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'));
    }
}
