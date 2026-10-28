<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Stringable;

/**
 * A calendar month, identified by its first day. Months are read on the Eastern
 * clock: a month starts at midnight Eastern on its 1st. Those moments are
 * returned as UTC instants, so Eastern time never leaks past this class.
 */
final class CalendarMonth implements Stringable
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
     * The month an instant falls in on the Eastern clock, for example the
     * month a participant joined in.
     */
    public static function ofInstant(DateTimeInterface $instant): self
    {
        return self::containing(CarbonImmutable::instance($instant)->tz(self::TIMEZONE));
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
     * The month it is right now in Eastern time.
     */
    public static function current(): self
    {
        return self::containing(CarbonImmutable::now(self::TIMEZONE));
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
     * The moment the month begins: midnight Eastern on its 1st.
     */
    public function startsAt(): CarbonImmutable
    {
        return $this->firstDay()->utc();
    }

    /**
     * The moment the month is over: midnight Eastern on the 1st of the next.
     */
    public function endsAt(): CarbonImmutable
    {
        return $this->next()->startsAt();
    }

    /**
     * The moment the Eastern clock reads the given hour on a day of the month,
     * for example 9am on the 15th. Like every moment here, it comes back as a
     * UTC instant.
     */
    public function at(int $day, int $hour): CarbonImmutable
    {
        $daysInMonth = $this->firstDay()->daysInMonth;

        if ($day < 1 || $day > $daysInMonth) {
            throw new InvalidArgumentException("Day must be between 1 and {$daysInMonth}, got {$day}.");
        }

        return $this->firstDay()->setDay($day)->setTime($hour, 0)->utc();
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

    public function isAfter(self $other): bool
    {
        return $this->toDateString() > $other->toDateString();
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
}
