<?php

use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

describe('parse()', function () {
    test('reads a stored first-of-month date', function () {
        $month = CalendarMonth::parse('2026-09-01');

        expect($month->year)->toBe(2026)
            ->and($month->month)->toBe(9);
    });

    test('refuses a date that does not exist', function () {
        CalendarMonth::parse('2026-02-31');
    })->throws(InvalidArgumentException::class);

    test('refuses a date that is not the first of the month', function () {
        CalendarMonth::parse('2026-09-15');
    })->throws(InvalidArgumentException::class);

    test('refuses something that is not a date', function () {
        CalendarMonth::parse('September');
    })->throws(InvalidArgumentException::class);
});

describe('parseYearMonth()', function () {
    test('reads a year and month', function () {
        expect(CalendarMonth::parseYearMonth('2026-09')->equals(CalendarMonth::of(2026, 9)))->toBeTrue();
    });

    test('refuses a month that does not exist', function () {
        CalendarMonth::parseYearMonth('2026-13');
    })->throws(InvalidArgumentException::class);

    test('refuses something that is not a date', function () {
        CalendarMonth::parseYearMonth('september');
    })->throws(InvalidArgumentException::class);
});

describe('of()', function () {
    test('refuses a month number outside 1 to 12', function () {
        CalendarMonth::of(2026, 13);
    })->throws(InvalidArgumentException::class);
});

describe('toDateString()', function () {
    test('is the first day of the month', function () {
        expect(CalendarMonth::of(2026, 3)->toDateString())->toBe('2026-03-01')
            ->and((string) CalendarMonth::of(2026, 3))->toBe('2026-03-01');
    });
});

describe('toYearMonth()', function () {
    test('is the year and zero-padded month', function () {
        expect(CalendarMonth::of(2026, 3)->toYearMonth())->toBe('2026-03');
    });
});

describe('label()', function () {
    test('names the month and year', function () {
        expect(CalendarMonth::of(2026, 9)->label())->toBe('September 2026');
    });
});

describe('name()', function () {
    test('names just the month', function () {
        expect(CalendarMonth::of(2026, 9)->name())->toBe('September');
    });
});

describe('dayLabel()', function () {
    test('labels the day in Eastern time, not UTC', function () {
        // 02:00Z on Oct 2 is 22:00 EDT on Oct 1.
        expect(CalendarMonth::dayLabel(CarbonImmutable::parse('2026-10-02T02:00:00Z')))->toBe('Oct 1')
            ->and(CalendarMonth::dayLabel(CarbonImmutable::parse('2026-10-02T05:00:00Z')))->toBe('Oct 2');
    });
});

describe('previous() and next()', function () {
    test('cross year boundaries', function () {
        expect(CalendarMonth::of(2026, 1)->previous()->equals(CalendarMonth::of(2025, 12)))->toBeTrue()
            ->and(CalendarMonth::of(2026, 12)->next()->equals(CalendarMonth::of(2027, 1)))->toBeTrue()
            ->and(CalendarMonth::of(2026, 3)->previous(14)->equals(CalendarMonth::of(2025, 1)))->toBeTrue();
    });
});

describe('opensAt()', function () {
    test('is midnight Eastern on the 1st of the next month, as a UTC instant', function () {
        expect(CalendarMonth::of(2026, 9)->opensAt()->toIso8601String())->toBe('2026-10-01T04:00:00+00:00');
    });
});

describe('revealDeadline()', function () {
    test('is the end of the following month in Eastern time, as a UTC instant', function () {
        expect(CalendarMonth::of(2026, 9)->revealDeadline()->toIso8601String())->toBe('2026-11-01T04:00:00+00:00')
            ->and(CalendarMonth::of(2026, 10)->revealDeadline()->toIso8601String())->toBe('2026-12-01T05:00:00+00:00');
    });
});

describe('hasOpened()', function () {
    test('opens at midnight Eastern, not midnight UTC', function () {
        $september = CalendarMonth::of(2026, 9);

        expect($september->hasOpened(CarbonImmutable::parse('2026-10-01T03:59:59Z')))->toBeFalse()
            ->and($september->hasOpened(CarbonImmutable::parse('2026-10-01T04:00:00Z')))->toBeTrue();
    });
});

describe('current() and latestOpen()', function () {
    test('read the month in Eastern time', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01T02:00:00Z'));

        expect(CalendarMonth::current()->equals(CalendarMonth::of(2026, 9)))->toBeTrue()
            ->and(CalendarMonth::latestOpen()->equals(CalendarMonth::of(2026, 8)))->toBeTrue();
    });
});
