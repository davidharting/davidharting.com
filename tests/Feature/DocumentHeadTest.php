<?php

use App\Models\Note;
use App\Models\Page;
use App\Models\User;
use Carbon\Carbon;
use Tests\Support\RenderedHead;
use Tests\TestCase;

/**
 * These tests parse the <head> back out of the rendered response, so they
 * prove tags actually reached the browser -- something Head::toArray() cannot
 * tell you, since it reports resolved metadata whether or not @head rendered
 * it.
 *
 * Parsing rather than string-matching keeps them indifferent to attribute
 * order and HTML escaping, which is where raw assertSeeHtml gets brittle.
 *
 * Where a whole family of tags matters (social cards especially), assert the
 * full map with toBe rather than picking out the two or three tags you happen
 * to be thinking about. That is the assertion that fails when a tag you did
 * not anticipate drifts.
 */
describe('defaults', function () {
    test('site-wide metadata renders on every page', function () {
        /** @var TestCase $this */
        $head = RenderedHead::from($this->get('/'));

        expect($head->title)->toBe("David Harting's Website")
            ->and($head->meta('description'))->toBe("David's Corner of the Internet")
            ->and($head->meta('robots'))->toBe('all')
            ->and($head->link('canonical'))->toBe('https://davidharting-dot-com.test/');
    });

    test('the default title renders without the site-name suffix', function () {
        /** @var TestCase $this */
        expect(RenderedHead::from($this->get('/'))->title)->toBe("David Harting's Website");
    });

    test('a page title inherits the site-name suffix', function () {
        /** @var TestCase $this */
        expect(RenderedHead::from($this->get('/notes'))->title)->toBe("David's Notes - davidharting.com");
    });

    test('every page advertises the atom feed', function () {
        /** @var TestCase $this */
        $head = RenderedHead::from($this->get('/media'));

        expect($head->links)->toContain([
            'rel' => 'alternate',
            'href' => 'http://davidharting-dot-com.test/feed',
            'attributes' => ['type' => 'application/atom+xml', 'title' => 'David Harting'],
        ]);
    });

    test('the PWA block renders', function () {
        /** @var TestCase $this */
        $head = RenderedHead::from($this->get('/'));

        expect($head->meta)->toMatchArray([
            'viewport' => 'width=device-width, initial-scale=1',
            'application-name' => 'David Harting',
            'apple-mobile-web-app-title' => 'David Harting',
            'apple-mobile-web-app-status-bar-style' => 'black',
            'mobile-web-app-capable' => 'yes',
            'theme-color' => '#1a1a2e',
        ]);

        expect($head->link('manifest'))->toBe('/manifest.json')
            ->and($head->link('apple-touch-icon'))->toBe('/icons/apple-touch-icon.png');
    });
});

describe('canonical urls', function () {
    test('a canonical url renders for the current page over https', function () {
        /** @var TestCase $this */
        expect(RenderedHead::from($this->get('/notes'))->link('canonical'))
            ->toBe('https://davidharting-dot-com.test/notes');
    });

    test('query strings are excluded so filtered media lists do not compete', function () {
        /** @var TestCase $this */
        expect(RenderedHead::from($this->get('/media?list=backlog&type=book'))->link('canonical'))
            ->toBe('https://davidharting-dot-com.test/media');
    });
});

describe('robots', function () {
    test('public pages are searchable', function () {
        /** @var TestCase $this */
        expect(RenderedHead::from($this->get('/notes'))->meta('robots'))->toBe('all');
    });

    test('private routes are hidden from robots', function (string $path) {
        /** @var TestCase $this */
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))->get($path);

        expect(RenderedHead::from($response)->meta('robots'))->toBe('noindex, nofollow');
    })->with(['/dashboard', '/profile', '/backend', '/kitchen-sink']);

    test('auth pages are hidden from robots', function () {
        /** @var TestCase $this */
        expect(RenderedHead::from($this->get('/login'))->meta('robots'))->toBe('noindex, nofollow');
    });

    test('an unpublished note is hidden from robots for the admin previewing it', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create(['visible' => false, 'title' => 'Draft']);
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/notes/'.$note->slug);

        expect(RenderedHead::from($response)->meta('robots'))->toBe('none');
    });
});

describe('notes', function () {
    beforeEach(function () {
        $this->note = Note::factory()->create([
            'visible' => true,
            'title' => 'A cool post',
            'lead' => 'You should read this',
            'published_at' => Carbon::parse('2000-02-01 12:00:00'),
        ]);
    });

    /**
     * Asserting the exhaustive og:/twitter:/article: map -- rather than the
     * handful of tags that come to mind -- is what catches a social tag
     * drifting. twitter:title falling back to the suffixed document title
     * instead of og:title was found exactly this way.
     */
    test('the full set of social tags renders', function () {
        /** @var TestCase $this */
        $head = RenderedHead::from($this->get('/notes/'.$this->note->slug));

        expect($head->metaMatching('og:', 'twitter:', 'article:'))->toBe([
            'og:type' => 'article',
            'og:site_name' => 'David Harting',
            'og:locale' => 'en_US',
            'og:title' => 'A cool post',
            'og:description' => "You should read this\n\nBy David Harting.\nPublished on 2000 February 1",
            'og:image' => 'http://davidharting-dot-com.test/headshot.jpg',
            'og:image:alt' => 'David Harting',
            'twitter:card' => 'summary',
            'twitter:title' => 'A cool post',
            'twitter:description' => "You should read this\n\nBy David Harting.\nPublished on 2000 February 1",
            'twitter:image' => 'http://davidharting-dot-com.test/headshot.jpg',
            'twitter:image:alt' => 'David Harting',
            'article:published_time' => '2000-02-01T12:00:00+00:00',
        ]);
    });

    test('social titles omit the site-name suffix carried by the document title', function () {
        /** @var TestCase $this */
        $head = RenderedHead::from($this->get('/notes/'.$this->note->slug));

        expect($head->title)->toBe('A cool post - davidharting.com')
            ->and($head->meta('og:title'))->toBe('A cool post')
            ->and($head->meta('twitter:title'))->toBe('A cool post');
    });

    test('a BlogPosting schema is emitted', function () {
        /** @var TestCase $this */
        $blogPosting = RenderedHead::from($this->get('/notes/'.$this->note->slug))->schema('BlogPosting');

        expect($blogPosting)->not->toBeNull()
            ->and($blogPosting['headline'])->toBe('A cool post')
            ->and($blogPosting['author']['name'])->toBe('David Harting')
            ->and($blogPosting['datePublished'])->toStartWith('2000-02-01');
    });

    test('a breadcrumb trail back to the notes index is emitted', function () {
        /** @var TestCase $this */
        $breadcrumbs = RenderedHead::from($this->get('/notes/'.$this->note->slug))->schema('BreadcrumbList');

        expect(array_column($breadcrumbs['itemListElement'], 'name'))
            ->toBe(['Home', 'Notes', 'A cool post']);
    });
});

describe('pages', function () {
    test('an unpublished page is hidden from robots', function () {
        /** @var TestCase $this */
        $page = Page::factory()->create(['is_published' => false, 'title' => 'Secret']);
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/pages/'.$page->slug);

        expect(RenderedHead::from($response)->meta('robots'))->toBe('none');
    });
});
