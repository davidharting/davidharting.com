<?php

namespace Tests\Support;

use DOMDocument;
use DOMElement;
use Illuminate\Testing\TestResponse;

/**
 * The <head> of a rendered response, parsed back out of the HTML.
 *
 * Asserting against this proves tags actually reached the browser, the way
 * assertSeeHtml does, but without depending on attribute order or HTML
 * escaping -- and it can enumerate a whole family of tags, so a tag nobody
 * thought to assert on still shows up in the diff.
 */
class RenderedHead
{
    /**
     * @param  array<string, string>  $meta  name/property => content
     * @param  array<int, array{rel: string, href: string, attributes: array<string, string>}>  $links
     * @param  array<int, array<string, mixed>>  $schemas
     */
    private function __construct(
        public readonly ?string $title,
        public readonly array $meta,
        public readonly array $links,
        public readonly array $schemas,
    ) {}

    /**
     * Parse the <head> out of a test response.
     *
     * ```php
     * $head = RenderedHead::from($this->get('/notes'));
     * expect($head->title)->toBe("David's Notes - davidharting.com");
     * ```
     */
    public static function from(TestResponse $response): self
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $head = $document->getElementsByTagName('head')->item(0);

        $title = $document->getElementsByTagName('title')->item(0)?->textContent;
        $meta = [];
        $links = [];
        $schemas = [];

        foreach ($head?->childNodes ?? [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            match ($node->tagName) {
                'meta' => self::collectMeta($node, $meta),
                'link' => self::collectLink($node, $links),
                'script' => self::collectSchema($node, $schemas),
                default => null,
            };
        }

        return new self($title, $meta, $links, $schemas);
    }

    /**
     * Get the content of a single meta tag, keyed by its name or property.
     *
     * Meta tags are indexed under whichever of the two they carry: HTML's own
     * `name` (description, robots, theme-color) or RDFa's `property`, which is
     * what Open Graph uses (og:title, article:published_time). A tag never
     * carries both in practice, so one flat lookup covers both spellings.
     *
     * Returns null for a tag that is absent -- which is a useful assertion in
     * its own right.
     *
     * ```php
     * expect($head->meta('robots'))->toBe('noindex, nofollow');
     * expect($head->meta('og:title'))->toBe('A cool post');
     * expect($head->meta('title'))->toBeNull();       // the dead tag is gone
     * ```
     */
    public function meta(string $key): ?string
    {
        return $this->meta[$key] ?? null;
    }

    /**
     * Get the href of the first link with the given rel.
     *
     * ```php
     * expect($head->link('canonical'))->toBe('https://davidharting.com/notes');
     * expect($head->link('manifest'))->toBe('/manifest.json');
     * ```
     */
    public function link(string $rel): ?string
    {
        foreach ($this->links as $link) {
            if ($link['rel'] === $rel) {
                return $link['href'];
            }
        }

        return null;
    }

    /**
     * Get every meta tag whose key starts with one of the given prefixes.
     *
     * Use this to assert on a whole family at once -- `toBe` against the full
     * map fails when a tag you did not anticipate appears or drifts, which a
     * handful of individual `meta()` assertions cannot do. This is how the
     * twitter:title regression was caught.
     *
     * ```php
     * expect($head->metaMatching('og:', 'twitter:'))->toBe([
     *     'og:type' => 'article',
     *     'og:title' => 'A cool post',
     *     'twitter:card' => 'summary',
     *     'twitter:title' => 'A cool post',
     * ]);
     * ```
     *
     * @return array<string, string>
     */
    public function metaMatching(string ...$prefixes): array
    {
        return array_filter(
            $this->meta,
            fn (string $key) => self::startsWithAny($key, $prefixes),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Get the decoded JSON-LD block for a schema.org type.
     *
     * Nothing to do with pages that return JSON. JSON-LD is structured data
     * for crawlers, embedded in an ordinary HTML page as
     * `<script type="application/ld+json">` -- it is how a search engine
     * learns that a page is a blog post with an author and a publish date.
     * Every page here is HTML; the JSON lives inside it.
     *
     * The block is already decoded, so assert on it as a plain array.
     *
     * ```php
     * $post = $head->schema('BlogPosting');
     * expect($post['headline'])->toBe('A cool post')
     *     ->and($post['author']['name'])->toBe('David Harting');
     * ```
     *
     * @return array<string, mixed>|null
     */
    public function schema(string $type): ?array
    {
        foreach ($this->schemas as $schema) {
            if (($schema['@type'] ?? null) === $type) {
                return $schema;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $prefixes
     */
    private static function startsWithAny(string $key, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Index a meta tag under its `name` or `property`, preferring `name`.
     *
     * A meta tag is legally allowed to carry neither -- `<meta charset>` and
     * `<meta http-equiv>` are the common cases -- so those are skipped rather
     * than indexed under an empty key. Nothing asserts on them: charset is
     * written directly in the layout because Laravel Head cannot express it.
     *
     * @param  array<string, string>  $meta
     */
    private static function collectMeta(DOMElement $node, array &$meta): void
    {
        $key = $node->getAttribute('name') ?: $node->getAttribute('property');

        if ($key !== '') {
            $meta[$key] = $node->getAttribute('content');
        }
    }

    /**
     * @param  array<int, array{rel: string, href: string, attributes: array<string, string>}>  $links
     */
    private static function collectLink(DOMElement $node, array &$links): void
    {
        $attributes = [];

        foreach ($node->attributes as $attribute) {
            if (! in_array($attribute->name, ['rel', 'href'], true)) {
                $attributes[$attribute->name] = $attribute->value;
            }
        }

        $links[] = [
            'rel' => $node->getAttribute('rel'),
            'href' => $node->getAttribute('href'),
            'attributes' => $attributes,
        ];
    }

    /**
     * Collect a JSON-LD block, ignoring every other kind of script.
     *
     * Deliberately only `application/ld+json`. That media type means "this is
     * structured data"; a plain `application/json` script would be application
     * payload, not schema.org, and letting it in here would put non-schema
     * entries into $schemas where `schema()` searches for an `@type`. It would
     * also hand arbitrary script bodies to a decoder that throws on invalid
     * JSON -- Vite's `<script type="module">` being the obvious neighbour.
     *
     * @param  array<int, array<string, mixed>>  $schemas
     */
    private static function collectSchema(DOMElement $node, array &$schemas): void
    {
        if ($node->getAttribute('type') !== 'application/ld+json') {
            return;
        }

        $schemas[] = json_decode($node->textContent, true, flags: JSON_THROW_ON_ERROR);
    }
}
