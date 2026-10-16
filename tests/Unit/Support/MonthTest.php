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

    test('reads a year and month', function () {
        expect(Month::parse('2026-09')->equals(Month::of(2026, 9)))->toBeTrue();
    });

    test('refuses a date that is not the first of the month', function () {
        Month::parse('2026-09-15');
    })->throws(InvalidArgumentException::class);

    test('refuses something that is not a date', function () {
        Month::parse('September');
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

describe('label()', function () {
    test('names the month and year', function () {
        expect(Month::of(2026, 9)->label())->toBe('September 2026');
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
    test('is midnight on the 1st of the next month in Eastern time', function () {
        $opensAt = Month::of(2026, 9)->opensAt();

        expect($opensAt->toIso8601String())->toBe('2026-10-01T00:00:00-04:00')
            ->and($opensAt->utc()->toIso8601String())->toBe('2026-10-01T04:00:00+00:00');
    });
});

describe('revealDeadline()', function () {
    test('is the end of the following month in Eastern time', function () {
        expect(Month::of(2026, 9)->revealDeadline()->toIso8601String())->toBe('2026-11-01T00:00:00-04:00')
            ->and(Month::of(2026, 10)->revealDeadline()->toIso8601String())->toBe('2026-12-01T00:00:00-05:00');
    });
});

describe('isOpen()', function () {
    test('opens at midnight Eastern, not midnight UTC', function () {
        $september = Month::of(2026, 9);

        expect($september->isOpen(CarbonImmutable::parse('2026-10-01T03:59:59Z')))->toBeFalse()
            ->and($september->isOpen(CarbonImmutable::parse('2026-10-01T04:00:00Z')))->toBeTrue();
    });
});

describe('current() and latestOpen()', function () {
    test('read the month in Eastern time', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01T02:00:00Z'));

        expect(Month::current()->equals(Month::of(2026, 9)))->toBeTrue()
            ->and(Month::latestOpen()->equals(Month::of(2026, 8)))->toBeTrue();
    });
});
