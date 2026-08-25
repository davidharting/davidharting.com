<?php

use App\Models\Media;
use App\Models\Note;
use App\Models\Page;
use App\Models\User;
use Carbon\Carbon;
use Tests\Support\RenderedHead;
use Tests\TestCase;

/**
 * Characterization tests for the document <head>.
 *
 * These parse the <head> back out of the rendered response, so they prove
 * tags actually reached the browser while staying indifferent to attribute
 * order and HTML escaping.
 *
 * Where a whole family of tags matters, assert the full map with toBe rather
 * than picking out the two or three tags that come to mind -- that is the
 * assertion that fails when a tag nobody anticipated appears or drifts.
 */
describe('site-wide tags', function () {
    test('the home page falls back to the site title and description', function () {
        /** @var TestCase $this */
        $head = RenderedHead::from($this->get('/'));

        expect($head->title)->toBe("David Harting's Website")
            ->and($head->meta('title'))->toBe("David Harting's Website")
            ->and($head->meta('description'))->toBe("David's Corner of the Internet")
            ->and($head->meta('og:description'))->toBe("David's Corner of the Internet");
    });

    test('the PWA tags render on every page', function () {
        /** @var TestCase $this */
        $head = RenderedHead::from($this->get('/'));

        expect($head->meta)->toMatchArray([
            'viewport' => 'width=device-width, initial-scale=1',
            'theme-color' => '#1a1a2e',
            'apple-mobile-web-app-capable' => 'yes',
            'apple-mobile-web-app-status-bar-style' => 'black',
            'apple-mobile-web-app-title' => 'David Harting',
        ]);

        expect($head->link('manifest'))->toBe('/manifest.json')
            ->and($head->link('apple-touch-icon'))->toBe('/icons/apple-touch-icon.png');
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
});

describe('page titles and descriptions', function () {
    test('index pages set their own title and description', function (string $path, string $title, string $description) {
        /** @var TestCase $this */
        $head = RenderedHead::from($this->get($path));

        expect($head->title)->toBe($title)
            ->and($head->meta('description'))->toBe($description);
    })->with([
        ['/notes', "David's Notes", 'Notes from David'],
        ['/media', "David's Media Log", 'I track what I read, watch, and play here!'],
        ['/pages', 'Pages', 'One-off pages on davidharting.com'],
    ]);

    test('a note uses its title and a description built from its lead', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create([
            'visible' => true,
            'title' => 'A cool post',
            'lead' => 'You should read this',
            'published_at' => Carbon::parse('2000-02-01 12:00:00'),
        ]);

        $head = RenderedHead::from($this->get('/notes/'.$note->slug));

        expect($head->title)->toBe('A cool post')
            ->and($head->meta('description'))
            ->toBe("You should read this\n\nBy David Harting.\nPublished on 2000 February 1");
    });

    test('a page uses its title', function () {
        /** @var TestCase $this */
        $page = Page::factory()->create(['is_published' => true, 'title' => 'About Us']);

        expect(RenderedHead::from($this->get('/pages/'.$page->slug))->title)->toBe('About Us');
    });

    test('a media item uses its title', function () {
        /** @var TestCase $this */
        $media = Media::factory()->create(['title' => 'Dune']);
        $response = $this->actingAs(User::factory()->admin()->create())->get('/media/'.$media->id);

        expect(RenderedHead::from($response)->title)->toBe('Dune');
    });
});

describe('robots', function () {
    test('the kitchen sink is hidden from robots', function () {
        /** @var TestCase $this */
        expect(RenderedHead::from($this->get('/kitchen-sink'))->meta('robots'))->toBe('noindex, nofollow');
    });

    test('public pages carry no robots directive', function (string $path) {
        /** @var TestCase $this */
        expect(RenderedHead::from($this->get($path))->meta('robots'))->toBeNull();
    })->with(['/', '/notes', '/media', '/pages']);
});

/**
 * Pinning what the head does *not* contain is what makes a swap of the
 * rendering implementation verifiable: a pure refactor has to leave these
 * absent, and adding any of them is a deliberate change.
 */
describe('tags the head does not yet emit', function () {
    test('no canonical url', function () {
        /** @var TestCase $this */
        expect(RenderedHead::from($this->get('/notes'))->link('canonical'))->toBeNull();
    });

    test('open graph carries only a description', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create(['visible' => true, 'title' => 'A cool post']);

        $head = RenderedHead::from($this->get('/notes/'.$note->slug));

        expect(array_keys($head->metaMatching('og:', 'twitter:', 'article:')))->toBe(['og:description']);
    });

    test('no JSON-LD structured data', function () {
        /** @var TestCase $this */
        $note = Note::factory()->create(['visible' => true, 'title' => 'A cool post']);

        expect(RenderedHead::from($this->get('/notes/'.$note->slug))->schemas)->toBeEmpty();
    });
});
