<?php

use App\Models\Note;
use App\Queries\GetNoteQuery;
use Illuminate\Foundation\Testing\TestCase;

test('finds a published note by slug', function () {
    /** @var TestCase $this */
    $note = Note::factory()->create(['title' => 'A published note', 'visible' => true]);

    expect((new GetNoteQuery($note->slug))->execute()?->id)->toBe($note->id);
});

test('returns null for a slug that does not exist', function () {
    /** @var TestCase $this */
    expect((new GetNoteQuery('does-not-exist'))->execute())->toBeNull();
});

test('treats a draft as absent by default', function () {
    /** @var TestCase $this */
    $note = Note::factory()->create(['title' => 'A draft', 'visible' => false]);

    expect((new GetNoteQuery($note->slug))->execute())->toBeNull();
});

test('finds a draft when asked', function () {
    /** @var TestCase $this */
    $note = Note::factory()->create(['title' => 'A draft', 'visible' => false]);

    expect((new GetNoteQuery($note->slug, includeDrafts: true))->execute()?->id)->toBe($note->id);
});

test('loads the markdown content', function () {
    /** @var TestCase $this */
    // Unlike a listing, reading one note is the case that wants the body, so
    // this query has no reason to withhold it.
    $note = Note::factory()->create(['visible' => true, 'markdown_content' => 'The body']);

    expect((new GetNoteQuery($note->slug))->execute()?->markdown_content)->toBe('The body');
});
