<?php

namespace Tests\Support;

/**
 * Fake Merriam-Webster Collegiate responses, shared by the client's tests
 * and the tools that use it.
 */
class MerriamWebsterFixtures
{
    /** Matches every Collegiate lookup, for Http::fake(). */
    public const string URL = 'https://www.dictionaryapi.com/api/v3/references/collegiate/json/*';

    /**
     * A trimmed entry in the shape Merriam-Webster sends. Real entries carry
     * much more, such as pronunciations and the full nested definition.
     *
     * @param  list<string>  $shortDefinitions
     * @return array<string, mixed>
     */
    public static function entry(string $headword, ?int $homograph, string $partOfSpeech, array $shortDefinitions): array
    {
        return [
            'meta' => [
                'id' => $homograph === null ? $headword : "{$headword}:{$homograph}",
                'stems' => [$headword, "{$headword}s"],
                'offensive' => false,
            ],
            ...($homograph === null ? [] : ['hom' => $homograph]),
            'hwi' => ['hw' => $headword],
            'fl' => $partOfSpeech,
            'shortdef' => $shortDefinitions,
        ];
    }
}
