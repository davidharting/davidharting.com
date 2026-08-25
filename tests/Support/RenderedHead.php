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
     * Get the content of a single meta tag, by its name or property attribute.
     */
    public function meta(string $key): ?string
    {
        return $this->meta[$key] ?? null;
    }

    /**
     * Get the href of the first link with the given rel.
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
     * map fails when a tag you did not anticipate appears or drifts.
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
