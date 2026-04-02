<?php

use App\Models\Note;
use Tests\TestCase;

test('feed endpoint returns 200', function () {
    /** @var TestCase $this */
    $response = $this->get('/feed');

    $response->assertStatus(200);
    $response->assertSeeText('Notes from David Harting');
});

test('feed contains visible posts in reverse chronological order', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'visible' => true,
        'title' => 'Oldest Post',
        'slug' => 'oldest-post',
        'published_at' => now()->subDays(3),
    ]);

    Note::factory()->create([
        'visible' => true,
        'title' => 'Middle Post',
        'slug' => 'middle-post',
        'published_at' => now()->subDays(2),
    ]);

    Note::factory()->create([
        'visible' => true,
        'title' => 'Newest Post',
        'slug' => 'newest-post',
        'published_at' => now()->subDays(1),
    ]);

    $this->get('/feed')->assertSeeInOrder(['Newest Post', 'Middle Post', 'Oldest Post']);
});

test('feed excludes invisible posts', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'visible' => false,
        'title' => 'Hidden Note',
        'slug' => 'hidden-note',
        'markdown_content' => 'Secret content',
    ]);

    $response = $this->get('/feed');
    $response->assertDontSee('Hidden Note');
    $response->assertDontSee('Secret content');
});

test('feed renders markdown as HTML', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'visible' => true,
        'title' => 'Post with Markdown',
        'markdown_content' => '**some bold text**',
        'slug' => 'markdown-post',
        'published_at' => now(),
    ]);

    $response = $this->get('/feed');
    $response->assertSeeHtml('<strong>some bold text</strong>');
});

test('feed renders linked headings without heading HTML tags', function () {
    /** @var TestCase $this */
    // Some RSS readers strip <h1>-<h6> tags AND their children (including <a> tags),
    // making linked headings completely invisible. The feed should not use heading tags.
    Note::factory()->create([
        'visible' => true,
        'title' => 'Post with Linked Headings',
        'slug' => 'linked-headings-post',
        'published_at' => now(),
        'markdown_content' => implode("\n\n", [
            '### [Section One](https://example.com/one)',
            'First section body.',
            '### [Section Two](https://example.com/two)',
            'Second section body.',
        ]),
    ]);

    $response = $this->get('/feed');

    // Heading text and links must be present
    $response->assertSeeText('Section One');
    $response->assertSeeText('Section Two');
    $response->assertSee('https://example.com/one');
    $response->assertSee('https://example.com/two');

    // But NOT wrapped in heading elements that RSS readers may strip with their children
    $response->assertDontSee('<h3>', false);
    $response->assertDontSee('<h4>', false);
    $response->assertDontSee('<h5>', false);
    $response->assertDontSee('<h6>', false);
});
