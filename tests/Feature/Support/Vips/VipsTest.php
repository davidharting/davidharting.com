<?php

use App\Support\Vips\Vips;
use App\Support\Vips\VipsFailed;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Tests\Support\PhotoInspector;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->workspace = (new TemporaryDirectory)->create();
});

afterEach(function () {
    /** @var TestCase $this */
    $this->workspace->delete();
});

describe('resize()', function () {
    test('fits the copy inside the square and returns its size', function () {
        /** @var TestCase $this */
        $output = $this->workspace->path('copy.jpg');

        $size = Vips::resize(PhotoInspector::fixture('iphone.jpg'), $output, 512, 80);

        expect($size)->toBe(['width' => 384, 'height' => 512])
            ->and(PhotoInspector::inspect($output))->toMatchArray(['width' => 384, 'height' => 512, 'mime' => 'image/jpeg']);
    });

    test('never enlarges a photo smaller than the square', function () {
        /** @var TestCase $this */
        $size = Vips::resize(PhotoInspector::fixture('iphone.jpg'), $this->workspace->path('copy.jpg'), 10_000, 80);

        expect($size)->toBe(['width' => 3024, 'height' => 4032]);
    });

    test('throws when the input is not an image', function () {
        /** @var TestCase $this */
        $input = $this->workspace->path('notes.jpg');
        file_put_contents($input, 'not really a jpeg');

        expect(fn () => Vips::resize($input, $this->workspace->path('copy.jpg'), 512, 80))
            ->toThrow(VipsFailed::class, 'vipsthumbnail failed');
    });
});
