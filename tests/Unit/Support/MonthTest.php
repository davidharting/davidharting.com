<?php

use App\Support\Month;
use Carbon\CarbonImmutable;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

describe('parse()', function () {
    test('reads a stored first-of-month date', function () {
        $month = Month::parse('2026-09-01');

        expect($month->year)->toBe(2026)
            ->and($month->month)->toBe(9);
    });

    test('refuses a date that does not exist', function () {
        Month::parse('2026-02-31');
    })->throws(InvalidArgumentException::class);

    test('refuses a date that is not the first of the month', function () {
        Month::parse('2026-09-15');
    })->throws(InvalidArgumentException::class);

    test('refuses something that is not a date', function () {
        Month::parse('September');
    })->throws(InvalidArgumentException::class);
});

describe('parseYearMonth()', function () {
    test('reads a year and month', function () {
        expect(Month::parseYearMonth('2026-09')->equals(Month::of(2026, 9)))->toBeTrue();
    });

    test('refuses a month that does not exist', function () {
        Month::parseYearMonth('2026-13');
    })->throws(InvalidArgumentException::class);
});

describe('of()', function () {
    test('refuses a month number outside 1 to 12', function () {
        Month::of(2026, 13);
    })->throws(InvalidArgumentException::class);
});

describe('toDateString()', function () {
    test('is the first day of the month', function () {
        expect(Month::of(2026, 3)->toDateString())->toBe('2026-03-01')
            ->and((string) Month::of(2026, 3))->toBe('2026-03-01');
    });
});

describe('toYearMonth()', function () {
    test('is the year and zero-padded month', function () {
        expect(Month::of(2026, 3)->toYearMonth())->toBe('2026-03');
    });
});

describe('label()', function () {
    test('names the month and year', function () {
        expect(Month::of(2026, 9)->label())->toBe('September 2026');
    });
});

describe('dayLabel()', function () {
    test('reads the day in Eastern time', function () {
        expect(Month::dayLabel(CarbonImmutable::parse('2026-10-02T02:00:00Z')))->toBe('Oct 1')
            ->and(Month::dayLabel(CarbonImmutable::parse('2026-10-02T05:00:00Z')))->toBe('Oct 2');
    });
});

describe('previous() and next()', function () {
    test('cross year boundaries', function () {
        expect(Month::of(2026, 1)->previous()->equals(Month::of(2025, 12)))->toBeTrue()
            ->and(Month::of(2026, 12)->next()->equals(Month::of(2027, 1)))->toBeTrue()
            ->and(Month::of(2026, 3)->previous(14)->equals(Month::of(2025, 1)))->toBeTrue();
    });
});

describe('opensAt()', function () {
    test('is midnight Eastern on the 1st of the next month, as a UTC instant', function () {
        expect(Month::of(2026, 9)->opensAt()->toIso8601String())->toBe('2026-10-01T04:00:00+00:00');
    });
});

describe('revealDeadline()', function () {
    test('is the end of the following month in Eastern time, as a UTC instant', function () {
        expect(Month::of(2026, 9)->revealDeadline()->toIso8601String())->toBe('2026-11-01T04:00:00+00:00')
            ->and(Month::of(2026, 10)->revealDeadline()->toIso8601String())->toBe('2026-12-01T05:00:00+00:00');
    });
});

describe('hasOpened()', function () {
    test('opens at midnight Eastern, not midnight UTC', function () {
        $september = Month::of(2026, 9);

        expect($september->hasOpened(CarbonImmutable::parse('2026-10-01T03:59:59Z')))->toBeFalse()
            ->and($september->hasOpened(CarbonImmutable::parse('2026-10-01T04:00:00Z')))->toBeTrue();
    });
});

describe('current() and latestOpen()', function () {
    test('read the month in Eastern time', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01T02:00:00Z'));

        expect(Month::current()->equals(Month::of(2026, 9)))->toBeTrue()
            ->and(Month::latestOpen()->equals(Month::of(2026, 8)))->toBeTrue();
    });
});
