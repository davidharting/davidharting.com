<?php

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

test('serves a file from a temporary URL on the local private disk', function () {
    /** @var TestCase $this */
    $disk = Storage::disk('private');
    $disk->put('private-disk-test/hello.txt', 'hello');

    try {
        $this->get($disk->temporaryUrl('private-disk-test/hello.txt', now()->addMinute()))
            ->assertOk()
            ->assertStreamedContent('hello');
    } finally {
        $disk->deleteDirectory('private-disk-test');
    }
});
