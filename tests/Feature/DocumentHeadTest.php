<?php

use App\Models\Note;
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
 *
 * Scope: this file holds the rules the head system owns and applies across
 * pages -- defaults, the title suffix, canonical behavior, the robots matrix,
 * the social-card contract. Assertions about what a *particular* page emits
 * live in that page's own suite (ShowNoteTest, ShowPageTest).
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

    test('every page advertises the atom feed', function (string $path) {
        /** @var TestCase $this */
        $head = RenderedHead::from($this->get($path));

        expect($head->links)->toContain([
            'rel' => 'alternate',
            'href' => 'http://davidharting-dot-com.test/feed',
            'attributes' => ['type' => 'application/atom+xml', 'title' => 'David Harting'],
        ]);
    })->with(['/', '/notes', '/media', '/pages']);

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

    test('the debug page is hidden from robots despite being public', function () {
        /** @var TestCase $this */
        $this->assertGuest();

        expect(RenderedHead::from($this->get('/debug'))->meta('robots'))->toBe('noindex, nofollow');
    });

    test('auth pages are hidden from robots', function () {
        /** @var TestCase $this */
        expect(RenderedHead::from($this->get('/login'))->meta('robots'))->toBe('noindex, nofollow');
    });

});

describe('request isolation', function () {
    /**
     * A controller's Head:: calls must not outlive its request. Production
     * guarantees this; the test layer only does because Tests\TestCase
     * simulates it -- see TestCase::call() for why and how we know.
     */
    test('runtime metadata from one request does not carry into the next', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create(['visible' => true, 'title' => 'A cool post']);

        $this->get('/notes/'.$note->slug);

        expect(RenderedHead::from($this->get('/notes'))->title)->toBe("David's Notes - davidharting.com");
    });
});

/**
 * A note is the fixture here, not the subject. These two assert rules the head
 * system owns -- the complete social-card contract, and how the title suffix
 * applies to social tags -- so they stay with the rest of the spec rather than
 * moving to ShowNoteTest with the per-record assertions.
 */
describe('social cards', function () {
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

});
