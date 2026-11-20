<?php

namespace App\Clients\MerriamWebster;

/**
 * One headword in a Merriam-Webster response, typed for the fields we use.
 *
 * An entry is a heading in the dictionary, not a single meaning: "bow" the
 * weapon, "bow" the verb and "bow" the front of a ship are three entries,
 * and each holds its own numbered senses. A search also returns entries
 * for related headwords, such as "rainbow" for "bow".
 *
 * Only the fields the app reads are typed here. The full definition
 * structure (`def`, with its nested sense sequences and inline markup) stays
 * in {@see self::$raw} until something needs it.
 *
 * @see https://dictionaryapi.com/products/json
 */
final readonly class Entry
{
    /**
     * @param  list<string>  $stems  the forms of the word this entry matches, such as plurals and tenses
     * @param  list<string>  $shortDefinitions  plain-text definitions of the entry's first three senses
     * @param  array<string, mixed>  $raw  the entry exactly as Merriam-Webster sent it
     */
    public function __construct(
        public string $id,
        public string $headword,
        public ?int $homograph,
        public ?string $partOfSpeech,
        public array $stems,
        public array $shortDefinitions,
        public bool $isOffensive,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $entry
     *
     * @throws MerriamWebsterFailed when the entry lacks its id or headword
     */
    public static function fromResponse(array $entry): self
    {
        $id = data_get($entry, 'meta.id');
        $headword = data_get($entry, 'hwi.hw');

        if (! is_string($id) || ! is_string($headword)) {
            throw new MerriamWebsterFailed('Merriam-Webster sent an entry without an id or headword.');
        }

        return new self(
            id: $id,
            // M-W marks syllable breaks with asterisks: "ap*ple".
            headword: str_replace('*', '', $headword),
            homograph: is_int($entry['hom'] ?? null) ? $entry['hom'] : null,
            partOfSpeech: is_string($entry['fl'] ?? null) ? $entry['fl'] : null,
            stems: array_values(array_filter((array) data_get($entry, 'meta.stems', []), is_string(...))),
            shortDefinitions: array_values(array_filter((array) ($entry['shortdef'] ?? []), is_string(...))),
            isOffensive: data_get($entry, 'meta.offensive') === true,
            raw: $entry,
        );
    }
}
