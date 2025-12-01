<?php

use App\Models\Page;
use Tests\TestCase;

describe('slug', function () {
    it('is respected if provided', function () {
        /** @var TestCase $this */
        $page = Page::factory()->create(['slug' => 'my-custom-slug']);
        expect($page->slug)->toBe('my-custom-slug');
    });

    it('is generated from title if not provided', function () {
        /** @var TestCase $this */
        $page = Page::factory()->create(['title' => 'My Page Title']);
        expect($page->slug)->toBe('my-page-title');
    });

    it('is generated as a ULID if only slug is not provided and title is empty', function () {
        /** @var TestCase $this */
        $page = Page::factory()->create(['title' => '', 'slug' => null]);
        // ULID should be 26 characters long
        expect($page->slug)->toHaveLength(26);
    });
});

describe('renderContent', function () {
    it('converts markdown to HTML correctly', function () {
        /** @var TestCase $this */
        $page = Page::factory()->create([
            'markdown_content' => "**Bold text**\n\n*Italic text*\n\n[Link](https://example.com)",
        ]);

        $rendered = $page->renderContent();
        expect($rendered)->toContain('<strong>Bold text</strong>');
        expect($rendered)->toContain('<em>Italic text</em>');
        expect($rendered)->toContain('<a href="https://example.com">Link</a>');
    });

    it('returns null when markdown_content is empty', function () {
        /** @var TestCase $this */
        $page = Page::factory()->create([
            'title' => 'Just a title',
            'markdown_content' => '',
        ]);

        $rendered = $page->renderContent();
        expect($rendered)->toBeNull();
    });

    it('allows HTML in markdown_content for semantic markup', function () {
        /** @var TestCase $this */
        $page = Page::factory()->create([
            'markdown_content' => '**Markdown** with <figure><img src="test.jpg" alt="Test"><figcaption>A caption</figcaption></figure>',
        ]);

        $rendered = $page->renderContent();
        expect($rendered)->toContain('<strong>Markdown</strong>');
        expect($rendered)->toContain('<figure>');
        expect($rendered)->toContain('<figcaption>');
        expect($rendered)->toContain('A caption');
    });
});

describe('is_published', function () {
    it('defaults to true when creating a new page', function () {
        /** @var TestCase $this */
        $page = Page::factory()->create();
        expect($page->is_published)->toBeTrue();
    });

    it('can be set to false', function () {
        /** @var TestCase $this */
        $page = Page::factory()->create(['is_published' => false]);
        expect($page->is_published)->toBeFalse();
    });
});
