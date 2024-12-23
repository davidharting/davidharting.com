<?php

use App\Enum\MediaTypeName;

test('displayNames', function () {
    /** @var TestCase $this */
    expect(MediaTypeName::displayNames())->toEqual([
        'Album',
        'Book',
        'Movie',
        'TV Show',
        'Video Game',
    ]);
});
