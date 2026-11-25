<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A fake Merriam-Webster Collegiate API at the network layer.
 *
 * answering() serves every lookup from a small dictionary of canned answers,
 * so a test states what M-W knows rather than wiring up each response. For
 * failure modes (error statuses, dropped connections, retries), fake
 * {@see self::URL} with Http::fake() directly.
 */
class FakeMerriamWebster
{
    /** Matches every Collegiate lookup, for Http::fake(). */
    public const string URL = 'https://www.dictionaryapi.com/api/v3/references/collegiate/json/*';

    /**
     * Serve each lookup from $answers, keyed by the term in the request.
     *
     * A term's answer is a list of entries when M-W knows the word, or a list
     * of suggested spellings when it doesn't. Terms missing from $answers get
     * an empty list, as M-W sends for gibberish.
     *
     * @param  array<string, list<array<string, mixed>|string>>  $answers
     */
    public static function answering(array $answers): void
    {
        Http::fake([
            self::URL => function (Request $request) use ($answers) {
                $term = rawurldecode(Str::afterLast((string) parse_url($request->url(), PHP_URL_PATH), '/'));

                return Http::response($answers[$term] ?? []);
            },
        ]);
    }

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
