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

test('feed renders linked headings in content element', function () {
    /** @var TestCase $this */
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

    // HTML body must be in <content>, not <summary type="html">
    $response->assertSee('<content type="html">', false);
    $response->assertDontSee('<summary type="html">', false);
});

test('lead appears as plain-text summary element', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'visible' => true,
        'title' => 'Post with Lead',
        'slug' => 'post-with-lead',
        'published_at' => now(),
        'lead' => 'A short teaser for this post.',
        'markdown_content' => 'Body of the post.',
    ]);

    $response = $this->get('/feed');

    // Lead must appear as a plain-text <summary> (no type attribute)
    $response->assertSee('<summary>A short teaser for this post.</summary>', false);
    // Must NOT be wrapped in type="html"
    $response->assertDontSee('<summary type="html">', false);
});

test('feed omits summary element when note has no lead', function () {
    /** @var TestCase $this */
    Note::factory()->noLead()->create([
        'visible' => true,
        'title' => 'Post Without Lead',
        'slug' => 'post-without-lead',
        'published_at' => now(),
        'markdown_content' => 'Body of the post.',
    ]);

    $response = $this->get('/feed');

    // No <summary> element at all when there is no lead
    $response->assertDontSee('<summary', false);
});

test('lead is not duplicated in content element', function () {
    /** @var TestCase $this */
    Note::factory()->create([
        'visible' => true,
        'title' => 'Post with Lead and Content',
        'slug' => 'post-lead-and-content',
        'published_at' => now(),
        'lead' => 'Plain text teaser.',
        'markdown_content' => 'Body of the post.',
    ]);

    $response = $this->get('/feed');

    // Lead lives in <summary> as plain text — must NOT also appear as HTML inside <content>
    $response->assertDontSee('<p><i>', false);
    // But the lead text itself must still be visible (in <summary>)
    $response->assertSeeText('Plain text teaser.');
});
