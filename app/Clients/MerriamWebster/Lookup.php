<?php

namespace App\Clients\MerriamWebster;

/**
 * Merriam-Webster's answer to one lookup.
 *
 * The API answers a known word with a list of entries and an unknown word
 * with a list of spelling suggestions, which may be empty. The decoded
 * response is kept whole in {@see self::$response} so it can be stored and
 * re-read later without spending another query.
 */
final readonly class Lookup
{
    /**
     * @param  list<Entry>  $entries
     * @param  list<string>  $suggestions
     * @param  list<array<string, mixed>|string>  $response  the decoded JSON, untouched
     */
    public function __construct(
        public string $term,
        public array $entries,
        public array $suggestions,
        public array $response,
    ) {}

    /**
     * @param  list<array<string, mixed>|string>  $response
     *
     * @throws MerriamWebsterFailed when an entry lacks its id or headword
     */
    public static function fromResponse(string $term, array $response): self
    {
        $entries = array_map(
            Entry::fromResponse(...),
            array_values(array_filter($response, is_array(...))),
        );

        return new self(
            term: $term,
            entries: $entries,
            suggestions: array_values(array_filter($response, is_string(...))),
            response: $response,
        );
    }

    public function isFound(): bool
    {
        return $this->entries !== [];
    }
}
