<?php

use App\Support\LikePattern;

test('plain text is unchanged', function () {
    expect(LikePattern::escape('hello world'))->toBe('hello world');
});

test('percent sign is escaped', function () {
    expect(LikePattern::escape('100%'))->toBe('100\\%');
});

test('underscore is escaped', function () {
    expect(LikePattern::escape('hello_world'))->toBe('hello\\_world');
});

test('backslash is escaped', function () {
    expect(LikePattern::escape('C:\\Users'))->toBe('C:\\\\Users');
});

test('multiple wildcards in the same string are all escaped', function () {
    expect(LikePattern::escape('50% off_sale'))->toBe('50\\% off\\_sale');
});
