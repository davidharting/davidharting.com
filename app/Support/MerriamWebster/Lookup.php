<?php

namespace App\Support\MerriamWebster;

/**
 * Merriam-Webster's answer to one lookup, kept as the decoded JSON it sent.
 *
 * The API answers a known word with a list of entry objects (one per
 * homograph or part of speech) and an unknown word with a list of spelling
 * suggestions, which may be empty. The raw response is kept whole so it can
 * be stored and re-read later without spending another query.
 */
final readonly class Lookup
{
    /**
     * @param  list<array<string, mixed>|string>  $response
     */
    public function __construct(
        public string $term,
        public array $response,
    ) {}

    public function isFound(): bool
    {
        return $this->entries() !== [];
    }

    /**
     * The dictionary entries for the term, or an empty list when it wasn't found.
     *
     * @return list<array<string, mixed>>
     */
    public function entries(): array
    {
        return array_values(array_filter($this->response, is_array(...)));
    }

    /**
     * Words Merriam-Webster suggests when the term wasn't found.
     *
     * @return list<string>
     */
    public function suggestions(): array
    {
        return array_values(array_filter($this->response, is_string(...)));
    }
}
