<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Stringable;

/**
 * A calendar month that memories are written about, stored as its first day.
 *
 * The clock rules live here and only here: a month opens on the 1st of the
 * next month and its reveal deadline is the end of the month after that,
 * both in Eastern time. Stored values never carry a time zone.
 */
final class Month implements Castable, Stringable
{
    public const TIMEZONE = 'America/New_York';

    private function __construct(
        public readonly int $year,
        public readonly int $month,
    ) {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Month must be between 1 and 12, got {$month}.");
        }
    }

    public static function of(int $year, int $month): self
    {
        return new self($year, $month);
    }

    /**
     * The month a date falls in, read in the date's own time zone.
     */
    public static function containing(DateTimeInterface $date): self
    {
        return new self((int) $date->format('Y'), (int) $date->format('n'));
    }

    /**
     * Parse a `Y-m` or first-of-month `Y-m-d` string, such as a stored `month` column.
     */
    public static function parse(string $value): self
    {
        if (! preg_match('/^(\d{4})-(\d{2})(?:-(\d{2}))?$/', $value, $parts)) {
            throw new InvalidArgumentException("Cannot parse a month from '{$value}'.");
        }

        if (isset($parts[3]) && $parts[3] !== '01') {
            throw new InvalidArgumentException("A month is stored as its first day, got '{$value}'.");
        }

        return new self((int) $parts[1], (int) $parts[2]);
    }

    /**
     * The month it is right now in Eastern time. It is not open yet.
     */
    public static function current(): self
    {
        return self::containing(CarbonImmutable::now(self::TIMEZONE));
    }

    /**
     * The most recent month that has opened for drafting.
     */
    public static function latestOpen(): self
    {
        return self::current()->previous();
    }

    public function previous(int $months = 1): self
    {
        return self::containing($this->firstDay()->subMonthsNoOverflow($months));
    }

    public function next(int $months = 1): self
    {
        return self::containing($this->firstDay()->addMonthsNoOverflow($months));
    }

    /**
     * Midnight on the 1st of this month, in Eastern time.
     */
    public function firstDay(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, $this->month, 1, 0, 0, 0, self::TIMEZONE);
    }

    /**
     * When this month opens for drafting and sharing: the moment it is over.
     */
    public function opensAt(): CarbonImmutable
    {
        return $this->firstDay()->addMonthNoOverflow();
    }

    /**
     * The fallback reveal: the end of the following month, whether or not both have shared.
     */
    public function revealDeadline(): CarbonImmutable
    {
        return $this->firstDay()->addMonthsNoOverflow(2);
    }

    public function isOpen(?DateTimeInterface $at = null): bool
    {
        return $this->opensAt()->lessThanOrEqualTo($at ?? CarbonImmutable::now());
    }

    /**
     * For example "September 2026".
     */
    public function label(): string
    {
        return $this->firstDay()->format('F Y');
    }

    /**
     * The stored form: the first day as `Y-m-d`.
     */
    public function toDateString(): string
    {
        return sprintf('%04d-%02d-01', $this->year, $this->month);
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->month === $other->month;
    }

    public function __toString(): string
    {
        return $this->toDateString();
    }

    /**
     * @param  array<int, mixed>  $arguments
     * @return CastsAttributes<self, self|string>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            /**
             * @param  array<string, mixed>  $attributes
             */
            public function get(Model $model, string $key, mixed $value, array $attributes): ?Month
            {
                return $value === null ? null : Month::parse(substr((string) $value, 0, 10));
            }

            /**
             * @param  array<string, mixed>  $attributes
             */
            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                return match (true) {
                    $value === null => null,
                    $value instanceof Month => $value->toDateString(),
                    $value instanceof DateTimeInterface => Month::containing($value)->toDateString(),
                    default => Month::parse((string) $value)->toDateString(),
                };
            }
        };
    }
}
