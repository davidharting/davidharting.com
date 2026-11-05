<?php

namespace App\Fsrs;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Random\Randomizer;

/**
 * The FSRS-6 spaced-repetition scheduler: given a card's schedule and how well
 * it was recalled, works out its new memory state and when it is next due.
 *
 * A line-by-line port of py-fsrs v6.3.2's `Scheduler`
 * (https://github.com/open-spaced-repetition/py-fsrs), tested against that
 * project's own expectations. Stateless apart from its settings, so one
 * instance can schedule every card. Parameter optimisation is not ported.
 *
 * Two deliberate differences from py-fsrs: steps are whole seconds, so a Hard
 * on the first of two steps waits their mean rounded to the second; and review
 * times in any time zone are converted to UTC rather than refused.
 *
 * App\Fsrs depends on nothing but PHP itself, not Laravel or Carbon, so it
 * can move into its own Composer package unchanged.
 */
final readonly class Scheduler
{
    /**
     * The 21 FSRS-6 model weights py-fsrs ships as `DEFAULT_PARAMETERS`.
     */
    public const DEFAULT_PARAMETERS = [
        0.212, 1.2931, 2.3065, 8.2956, 6.4133, 0.8334, 3.0194, 0.001, 1.8722, 0.1666, 0.796,
        1.4835, 0.0614, 0.2629, 1.6483, 0.6014, 1.8729, 0.5425, 0.0912, 0.0658, 0.1542,
    ];

    public const STABILITY_MIN = 0.001;

    private const MIN_DIFFICULTY = 1.0;

    private const MAX_DIFFICULTY = 10.0;

    private const LOWER_BOUNDS = [
        self::STABILITY_MIN, self::STABILITY_MIN, self::STABILITY_MIN, self::STABILITY_MIN,
        1.0, 0.001, 0.001, 0.001, 0.0, 0.0, 0.001, 0.001, 0.001, 0.001, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.1,
    ];

    private const UPPER_BOUNDS = [
        100.0, 100.0, 100.0, 100.0,
        10.0, 4.0, 4.0, 0.75, 4.5, 0.8, 3.5, 5.0, 0.25, 0.9, 4.0, 1.0, 6.0, 2.0, 2.0, 0.8, 0.8,
    ];

    /**
     * How much a review interval is fuzzed, by the size of the interval in days.
     *
     * @var list<array{start: float, end: float, factor: float}>
     */
    private const FUZZ_RANGES = [
        ['start' => 2.5, 'end' => 7.0, 'factor' => 0.15],
        ['start' => 7.0, 'end' => 20.0, 'factor' => 0.1],
        ['start' => 20.0, 'end' => INF, 'factor' => 0.05],
    ];

    private const SECONDS_PER_DAY = 86400;

    private float $decay;

    private float $factor;

    /**
     * @param  list<float>  $parameters  The 21 FSRS-6 model weights.
     * @param  float  $desiredRetention  The recall probability a card should have when it falls due.
     * @param  list<int>  $learningSteps  Seconds between reviews while a card is first learned.
     * @param  list<int>  $relearningSteps  Seconds between reviews while a forgotten card is relearned.
     * @param  int  $maximumInterval  The most days ahead a card can be scheduled.
     * @param  bool  $enableFuzzing  Whether to nudge review intervals by a random day or few, so cards learned together drift apart.
     */
    public function __construct(
        public array $parameters = self::DEFAULT_PARAMETERS,
        public float $desiredRetention = 0.9,
        public array $learningSteps = [60, 600],
        public array $relearningSteps = [600],
        public int $maximumInterval = 36500,
        public bool $enableFuzzing = true,
        private Randomizer $randomizer = new Randomizer,
    ) {
        self::validateParameters($parameters);

        if (! ($desiredRetention > 0 && $desiredRetention < 1)) {
            throw new InvalidArgumentException("Desired retention must be between 0 and 1 exclusive, got {$desiredRetention}.");
        }

        self::validateSteps($learningSteps, 'Learning');
        self::validateSteps($relearningSteps, 'Relearning');

        if ($maximumInterval < 1) {
            throw new InvalidArgumentException("The maximum interval must be at least 1 day, got {$maximumInterval}.");
        }

        $this->decay = -$parameters[20];
        $this->factor = 0.9 ** (1 / $this->decay) - 1;
    }

    /**
     * Review a card: work out its new memory state and when it is next due.
     */
    public function review(CardSchedule $card, Rating $rating, DateTimeInterface $reviewedAt): CardSchedule
    {
        $reviewedAt = CardSchedule::inUtc($reviewedAt);
        $daysSinceLastReview = $card->lastReview === null ? null : self::wholeDaysBetween($card->lastReview, $reviewedAt);
        $reviewedSameDay = $daysSinceLastReview !== null && $daysSinceLastReview < 1;

        if ($card->stability === null || $card->difficulty === null) {
            $stability = $this->initialStability($rating);
            $difficulty = $this->clampDifficulty($this->initialDifficulty($rating));
        } else {
            $stability = $reviewedSameDay
                ? $this->shortTermStability($card->stability, $rating)
                : $this->nextStability($card->difficulty, $card->stability, $this->retrievability($card, $reviewedAt), $rating);
            $difficulty = $this->nextDifficulty($card->difficulty, $rating);
        }

        [$state, $step, $intervalSeconds] = match ($card->state) {
            CardState::Learning => $this->stepThrough($this->learningSteps, $card->step, CardState::Learning, $rating, $stability),
            CardState::Relearning => $this->stepThrough($this->relearningSteps, $card->step, CardState::Relearning, $rating, $stability),
            CardState::Review => $this->fromReview($rating, $stability),
        };

        if ($this->enableFuzzing && $state === CardState::Review) {
            $intervalSeconds = $this->fuzzedIntervalDays(intdiv($intervalSeconds, self::SECONDS_PER_DAY)) * self::SECONDS_PER_DAY;
        }

        return new CardSchedule(
            state: $state,
            step: $step,
            stability: $stability,
            difficulty: $difficulty,
            due: $reviewedAt->modify("+{$intervalSeconds} seconds"),
            lastReview: $reviewedAt,
        );
    }

    /**
     * The predicted probability, from 0 to 1, that the card is recalled at
     * the given moment. A card that has never been reviewed scores 0.
     */
    public function retrievability(CardSchedule $card, DateTimeInterface $at): float
    {
        $at = CardSchedule::inUtc($at);

        if ($card->lastReview === null || $card->stability === null) {
            return 0.0;
        }

        $elapsedDays = max(0, self::wholeDaysBetween($card->lastReview, $at));

        return (1 + $this->factor * $elapsedDays / $card->stability) ** $this->decay;
    }

    /**
     * The next state, step and interval in whole seconds for a card moving
     * through learning or relearning steps. Leaves for Review once the last
     * step is passed, or straight away on Easy. A card can sit on a step
     * past the end of the list if it was scheduled with more steps than this
     * scheduler has: anything but Again graduates it.
     *
     * @param  list<int>  $steps
     * @return array{CardState, ?int, int}
     */
    private function stepThrough(array $steps, int $step, CardState $stepState, Rating $rating, float $stability): array
    {
        $graduate = fn (): array => [CardState::Review, null, $this->nextIntervalDays($stability) * self::SECONDS_PER_DAY];
        $stepCount = count($steps);

        if ($stepCount === 0 || ($step >= $stepCount && $rating !== Rating::Again)) {
            return $graduate();
        }

        return match ($rating) {
            Rating::Again => [$stepState, 0, $steps[0]],
            Rating::Hard => [$stepState, $step, match (true) {
                $step === 0 && $stepCount === 1 => (int) round($steps[0] * 1.5),
                $step === 0 => (int) round(($steps[0] + $steps[1]) / 2),
                default => $steps[$step],
            }],
            Rating::Good => $step + 1 === $stepCount
                ? $graduate()
                : [$stepState, $step + 1, $steps[$step + 1]],
            Rating::Easy => $graduate(),
        };
    }

    /**
     * The next state, step and interval in whole seconds for a card reviewed
     * in the Review state. Forgetting it sends it to relearning, if there are
     * relearning steps.
     *
     * @return array{CardState, ?int, int}
     */
    private function fromReview(Rating $rating, float $stability): array
    {
        if ($rating === Rating::Again && $this->relearningSteps !== []) {
            return [CardState::Relearning, 0, $this->relearningSteps[0]];
        }

        return [CardState::Review, null, $this->nextIntervalDays($stability) * self::SECONDS_PER_DAY];
    }

    private function initialStability(Rating $rating): float
    {
        return $this->clampStability($this->parameters[$rating->value - 1]);
    }

    private function initialDifficulty(Rating $rating): float
    {
        return $this->parameters[4] - M_E ** ($this->parameters[5] * ($rating->value - 1)) + 1;
    }

    private function nextIntervalDays(float $stability): int
    {
        $interval = ($stability / $this->factor) * ($this->desiredRetention ** (1 / $this->decay) - 1);

        return self::roundHalfEven(min(max($interval, 1), $this->maximumInterval));
    }

    private function shortTermStability(float $stability, Rating $rating): float
    {
        $increase = M_E ** ($this->parameters[17] * ($rating->value - 3 + $this->parameters[18]))
            * $stability ** -$this->parameters[19];

        if ($rating !== Rating::Again) {
            $increase = max($increase, 1.0);
        }

        return $this->clampStability($stability * $increase);
    }

    /**
     * Nudge difficulty up or down by the rating, damped as it nears 10, then
     * revert it a little towards the initial difficulty of an Easy card.
     */
    private function nextDifficulty(float $difficulty, Rating $rating): float
    {
        $deltaDifficulty = -($this->parameters[6] * ($rating->value - 3));
        $dampedDifficulty = $difficulty + (10.0 - $difficulty) * $deltaDifficulty / 9.0;

        $next = $this->parameters[7] * $this->initialDifficulty(Rating::Easy)
            + (1 - $this->parameters[7]) * $dampedDifficulty;

        return $this->clampDifficulty($next);
    }

    private function nextStability(float $difficulty, float $stability, float $retrievability, Rating $rating): float
    {
        $next = $rating === Rating::Again
            ? $this->nextForgetStability($difficulty, $stability, $retrievability)
            : $this->nextRecallStability($difficulty, $stability, $retrievability, $rating);

        return $this->clampStability($next);
    }

    private function nextForgetStability(float $difficulty, float $stability, float $retrievability): float
    {
        $longTerm = $this->parameters[11]
            * $difficulty ** -$this->parameters[12]
            * (($stability + 1) ** $this->parameters[13] - 1)
            * M_E ** ((1 - $retrievability) * $this->parameters[14]);

        $shortTerm = $stability / M_E ** ($this->parameters[17] * $this->parameters[18]);

        return min($longTerm, $shortTerm);
    }

    private function nextRecallStability(float $difficulty, float $stability, float $retrievability, Rating $rating): float
    {
        $hardPenalty = $rating === Rating::Hard ? $this->parameters[15] : 1;
        $easyBonus = $rating === Rating::Easy ? $this->parameters[16] : 1;

        return $stability * (
            1
            + M_E ** $this->parameters[8]
            * (11 - $difficulty)
            * $stability ** -$this->parameters[9]
            * (M_E ** ((1 - $retrievability) * $this->parameters[10]) - 1)
            * $hardPenalty
            * $easyBonus
        );
    }

    /**
     * Nudge an interval of a few days or more to a random nearby length, so
     * cards learned together don't stay due together.
     */
    private function fuzzedIntervalDays(int $intervalDays): int
    {
        if ($intervalDays < 2.5) {
            return $intervalDays;
        }

        [$minDays, $maxDays] = $this->fuzzRange($intervalDays);
        $fuzzed = $this->randomizer->nextFloat() * ($maxDays - $minDays + 1) + $minDays;

        return self::roundHalfEven(min($fuzzed, $this->maximumInterval));
    }

    /**
     * The shortest and longest interval, in days, fuzzing may pick.
     *
     * @return array{int, int}
     */
    private function fuzzRange(int $intervalDays): array
    {
        $delta = 1.0;
        foreach (self::FUZZ_RANGES as $range) {
            $delta += $range['factor'] * max(min((float) $intervalDays, $range['end']) - $range['start'], 0.0);
        }

        $minDays = max(2, self::roundHalfEven($intervalDays - $delta));
        $maxDays = min(self::roundHalfEven($intervalDays + $delta), $this->maximumInterval);

        return [min($minDays, $maxDays), $maxDays];
    }

    private function clampDifficulty(float $difficulty): float
    {
        return min(max($difficulty, self::MIN_DIFFICULTY), self::MAX_DIFFICULTY);
    }

    private function clampStability(float $stability): float
    {
        return max($stability, self::STABILITY_MIN);
    }

    /**
     * Python's `round()`, which py-fsrs relies on: halves go to the even
     * neighbour. Callers clamp first, so the cast to int cannot overflow.
     */
    private static function roundHalfEven(float $value): int
    {
        return (int) round($value, 0, PHP_ROUND_HALF_EVEN);
    }

    /**
     * Whole days from one moment to a later one, rounded down, as Python's
     * `timedelta.days` does.
     */
    private static function wholeDaysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $seconds = ((int) $to->format('U') - (int) $from->format('U'))
            + ((int) $to->format('u') - (int) $from->format('u')) / 1_000_000;

        return (int) floor($seconds / self::SECONDS_PER_DAY);
    }

    /**
     * @param  array<int, float>  $parameters
     */
    private static function validateParameters(array $parameters): void
    {
        if (! array_is_list($parameters) || count($parameters) !== count(self::LOWER_BOUNDS)) {
            throw new InvalidArgumentException(sprintf('Expected %d FSRS parameters, got %d.', count(self::LOWER_BOUNDS), count($parameters)));
        }

        $errors = [];
        foreach ($parameters as $index => $parameter) {
            // Written this way round so NAN, which fails every comparison, is refused.
            if (! ($parameter >= self::LOWER_BOUNDS[$index] && $parameter <= self::UPPER_BOUNDS[$index])) {
                $errors[] = sprintf('parameters[%d] = %s is out of bounds: (%s, %s)', $index, $parameter, self::LOWER_BOUNDS[$index], self::UPPER_BOUNDS[$index]);
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException("One or more FSRS parameters are out of bounds:\n".implode("\n", $errors));
        }
    }

    /**
     * @param  array<int, int>  $steps
     */
    private static function validateSteps(array $steps, string $name): void
    {
        foreach ($steps as $step) {
            if (! is_int($step) || $step < 1) {
                throw new InvalidArgumentException("{$name} steps must be whole numbers of seconds, at least 1.");
            }
        }

        if (! array_is_list($steps)) {
            throw new InvalidArgumentException("{$name} steps must be a list.");
        }
    }
}
