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

describe('ofInstant()', function () {
    test('reads the instant on the Eastern clock, not UTC', function () {
        // 02:00Z on Nov 1 is 22:00 EDT on Oct 31.
        expect(CalendarMonth::ofInstant(CarbonImmutable::parse('2026-11-01T02:00:00Z')))->toEqual(CalendarMonth::of(2026, 10))
            ->and(CalendarMonth::ofInstant(CarbonImmutable::parse('2026-11-01T05:00:00Z')))->toEqual(CalendarMonth::of(2026, 11));
    });
});

describe('previous() and next()', function () {
    test('cross year boundaries', function () {
        expect(CalendarMonth::of(2026, 1)->previous()->equals(CalendarMonth::of(2025, 12)))->toBeTrue()
            ->and(CalendarMonth::of(2026, 12)->next()->equals(CalendarMonth::of(2027, 1)))->toBeTrue()
            ->and(CalendarMonth::of(2026, 3)->previous(14)->equals(CalendarMonth::of(2025, 1)))->toBeTrue();
    });
});

describe('startsAt() and endsAt()', function () {
    test('are midnight Eastern on the 1st of the month and of the next, as UTC instants', function () {
        $september = CalendarMonth::of(2026, 9);

        expect($september->startsAt()->toIso8601String())->toBe('2026-09-01T04:00:00+00:00')
            ->and($september->endsAt()->toIso8601String())->toBe('2026-10-01T04:00:00+00:00');
    });

    test('follow daylight saving time', function () {
        expect(CalendarMonth::of(2026, 10)->endsAt()->toIso8601String())->toBe('2026-11-01T04:00:00+00:00')
            ->and(CalendarMonth::of(2026, 11)->endsAt()->toIso8601String())->toBe('2026-12-01T05:00:00+00:00');
    });
});

describe('at()', function () {
    test('is the given hour Eastern on the given day, as a UTC instant that follows daylight saving', function () {
        expect(CalendarMonth::of(2026, 11)->at(day: 1, hour: 9)->toIso8601String())->toBe('2026-11-01T14:00:00+00:00')
            ->and(CalendarMonth::of(2027, 3)->at(day: 15, hour: 9)->toIso8601String())->toBe('2027-03-15T13:00:00+00:00');
    });

    test('refuses a day the month does not have', function () {
        CalendarMonth::of(2026, 11)->at(day: 31, hour: 9);
    })->throws(InvalidArgumentException::class);
});

describe('isAfter()', function () {
    test('compares months across years', function () {
        expect(CalendarMonth::of(2027, 1)->isAfter(CalendarMonth::of(2026, 12)))->toBeTrue()
            ->and(CalendarMonth::of(2026, 12)->isAfter(CalendarMonth::of(2027, 1)))->toBeFalse()
            ->and(CalendarMonth::of(2026, 9)->isAfter(CalendarMonth::of(2026, 9)))->toBeFalse();
    });
});

describe('current()', function () {
    test('reads the month in Eastern time', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01T02:00:00Z'));

        expect(CalendarMonth::current()->equals(CalendarMonth::of(2026, 9)))->toBeTrue();
    });
});
