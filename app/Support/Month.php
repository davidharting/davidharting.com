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
 * both at midnight Eastern. Those moments are returned as UTC instants, so
 * Eastern time never leaks past this class.
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
     * The month a date or instant falls in, read in its own time zone. Accepts
     * any PHP date object: Carbon, CarbonImmutable, DateTime or DateTimeImmutable.
     */
    public static function containing(DateTimeInterface $date): self
    {
        return new self((int) $date->format('Y'), (int) $date->format('n'));
    }

    /**
     * Parse a first-of-month `Y-m-d` string such as "2026-09-01". The `month`
     * column is a Postgres `date`, but PDO hands every date to PHP as a string.
     */
    public static function parse(string $value): self
    {
        if (! CarbonImmutable::canBeCreatedFromFormat($value, '!Y-m-d')) {
            throw new InvalidArgumentException("Cannot parse a month from '{$value}'.");
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        if ($date->day !== 1) {
            throw new InvalidArgumentException("A month is stored as its first day, got '{$value}'.");
        }

        return self::containing($date);
    }

    /**
     * Parse a `Y-m` string such as "2026-09", the form a month takes in URLs.
     */
    public static function parseYearMonth(string $value): self
    {
        return self::parse("{$value}-01");
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
     * When this month opens for drafting and sharing: the moment it is over.
     */
    public function opensAt(): CarbonImmutable
    {
        return $this->firstDay()->addMonthNoOverflow()->utc();
    }

    /**
     * The fallback reveal: the end of the following month, whether or not both have shared.
     */
    public function revealDeadline(): CarbonImmutable
    {
        return $this->firstDay()->addMonthsNoOverflow(2)->utc();
    }

    /**
     * Whether the month has opened for drafting and sharing. Once open, a
     * month stays open.
     */
    public function hasOpened(?DateTimeInterface $at = null): bool
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
     * Just the month's name, for example "September".
     */
    public function name(): string
    {
        return $this->firstDay()->format('F');
    }

    /**
     * The calendar day an instant falls on in Eastern time, for example "Oct 2".
     * Lives here so display code never has to name the time zone.
     */
    public static function dayLabel(DateTimeInterface $at): string
    {
        return CarbonImmutable::instance($at)->tz(self::TIMEZONE)->format('M j');
    }

    /**
     * The stored form: the first day as `Y-m-d`, for example "2026-09-01".
     */
    public function toDateString(): string
    {
        return sprintf('%04d-%02d-01', $this->year, $this->month);
    }

    /**
     * The month as `Y-m`, for example "2026-09", the form a month takes in URLs.
     */
    public function toYearMonth(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
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
     * Midnight on the 1st of this month, in Eastern time.
     */
    private function firstDay(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, $this->month, 1, 0, 0, 0, self::TIMEZONE);
    }

    /**
     * Lets a model list Month::class in its casts(), so reading the column
     * gives a Month and writing a Month (or a date) stores its first day.
     * Laravel calls this when it builds the cast; see "Castables" in the
     * Eloquent mutators docs.
     *
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
