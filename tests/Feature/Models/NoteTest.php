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
        $this->assertStringStartsWith('01etxk', $note->slug);
    });
});

describe('title, lead, or content must be provided', function () {
    it('is valid if title is provided', function () {
        Note::factory()->create(['title' => 'My Title', 'lead' => null, 'content' => null]);
    })->throwsNoExceptions();

    it('is valid if lead is provided', function () {
        Note::factory()->create(['lead' => 'My Lead', 'content' => null, 'title' => null]);
    })->throwsNoExceptions();

    it('is valid if content is provided', function () {
        /** @var TestCase $this */
        Note::factory()->create(['content' => 'My Content', 'title' => null, 'lead' => null]);
    })->throwsNoExceptions();

    it('is invalid if none are provided', function () {
        expect(Note::factory()->create(['title' => null, 'lead' => null, 'content' => null]));
    })->throws('Illuminate\Database\QueryException');
});
