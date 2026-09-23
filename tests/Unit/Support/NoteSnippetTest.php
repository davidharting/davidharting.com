<?php

use App\Models\Note;
use App\Support\NoteSnippet;

test('returns the content around the first match, with ellipses where it was cut', function () {
    $before = str_repeat('a', 200);
    $after = str_repeat('b', 200);
    $note = new Note(['markdown_content' => "{$before} xylophone {$after}"]);

    $snippet = NoteSnippet::for($note, 'xylophone');

    expect($snippet)->toStartWith('…')
        ->toEndWith('…')
        ->toContain('xylophone');
});

test('matches case-insensitively', function () {
    $note = new Note(['markdown_content' => 'I bought a Xylophone.']);

    expect(NoteSnippet::for($note, 'xylophone'))->toBe('I bought a Xylophone.');
});

test('omits the ellipses when the whole body fits', function () {
    $note = new Note(['markdown_content' => 'A short body about a xylophone.']);

    expect(NoteSnippet::for($note, 'xylophone'))->toBe('A short body about a xylophone.');
});

test('is null when the body does not contain the query', function () {
    // The match was in the title or lead, which a search result already carries.
    $note = new Note(['title' => 'Xylophone', 'markdown_content' => 'Nothing to see here.']);

    expect(NoteSnippet::for($note, 'xylophone'))->toBeNull();
});

test('is null when the note has no body', function () {
    $note = new Note(['markdown_content' => null]);

    expect(NoteSnippet::for($note, 'xylophone'))->toBeNull();
});
