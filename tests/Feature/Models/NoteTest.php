<?php

use App\Models\Note;
use Carbon\Carbon;
use Tests\TestCase;

use function PHPUnit\Framework\assertStringContainsString;

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

describe('published_at', function () {
    it('is cast to Carbon', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create(['published_at' => '2021-01-01']);
        expect($note->published_at)->toBeInstanceOf(Carbon::class);
    });
});

describe('publicationDate', function () {
    it('returns the Y M D', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create(['published_at' => Carbon::create(2000, 02, 01)]);
        expect($note->publicationDate())->toBe('2000 February 1');
    });
});

describe('toFeedItem', function () {
    it('works', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create([
            'title' => 'My Note Title',
            'lead' => 'Captivating lead',
            'content' => 'This is the content of the note.',
        ]);


        $item = $note->toFeedItem();
        expect($item->title)->toBe($note->title);
        expect($item->id)->toBe($note->slug);
        expect($item->updated)->toEqual($note->published_at);
        expect($item->link)->toBe(route('notes.show', $note->slug));
        expect($item->authorName)->toBe('David Harting');
        expect($item->authorEmail)->toBe('connect@davidharting.com');


        assertStringContainsString($note->title, $item->summary);
        assertStringContainsString($note->lead, $item->summary);
        assertStringContainsString($note->content, $item->summary);
    });
});
