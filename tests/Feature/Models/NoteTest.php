<?php

use App\Models\Note;
use Carbon\Carbon;
use Tests\TestCase;

describe('slug', function () {
    it('is respected if provided', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create(['slug' => 'my-slug']);
        expect($note->slug)->toBe('my-slug');
    });

    it('is generated from title if not provided', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create(['title' => 'My Title']);
        expect($note->slug)->toBe('my-title');
    });

    it('is generated from published_at if title is not provided', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create(['title' => null, 'published_at' => Carbon::parse('2021-01-01')]);
        $this->assertStringStartsWith('01ETXK', $note->slug);
    });
});
