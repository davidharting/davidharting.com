<?php

use App\Models\Note;
use Tests\TestCase;

test('empty feed', function () {
    /** @var TestCase $this */
    $response = $this->get('/feed');

    $response->assertStatus(200);
    $response->assertSeeTextInOrder([
        'Notes and media log updates from David Harting',
    ]);
});

test('Invisible posts do not show up', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'visible' => false,
        'title' => 'Hidden Note',
        'slug' => 'hidden-note',
        'content' => 'John Cena',
    ]);

    $response = $this->get('/feed');
    $response->assertDontSeeText('Hidden Note');
    $response->assertDontSeeText('John Cena');
});

test('Posts are in reverse chronological order', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'visible' => true,
        'title' => 'Oldest Post',
        'content' => '**some bold text**',
        'slug' => 'oldest-post',
        'published_at' => now()->subDays(2),
    ]);

    $response = $this->get('/feed');
    $response->dump();
    $response->assertSeeHtml([
        'Oldest Post',
        '**some bold text**',
    ]);
});
