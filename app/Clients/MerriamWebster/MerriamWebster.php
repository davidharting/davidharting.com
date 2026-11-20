<?php

namespace App\Clients\MerriamWebster;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Looks words up in Merriam-Webster's Collegiate Dictionary API.
 *
 * The free non-commercial key allows 1,000 queries a day, so callers should
 * keep the response they get rather than look the same word up again.
 *
 * @see https://dictionaryapi.com/products/api-collegiate-dictionary
 */
class MerriamWebster
{
    private const string BASE_URL = 'https://www.dictionaryapi.com/api/v3/references/collegiate/json';

    public function __construct(
        #[Config('services.merriam_webster.collegiate_key')]
        private ?string $key,
    ) {}

    /**
     * @throws InvalidArgumentException when the term is blank
     * @throws MerriamWebsterFailed when the key is missing or rejected, or the request fails
     */
    public function lookUp(string $term): Lookup
    {
        $term = trim($term);

        if ($term === '') {
            throw new InvalidArgumentException('A dictionary lookup needs a term.');
        }

        if (blank($this->key)) {
            throw new MerriamWebsterFailed('No Merriam-Webster key is configured. Set MERRIAM_WEBSTER_COLLEGIATE_KEY.');
        }

        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->withUrlParameters(['term' => $term])
                ->get('{term}', ['key' => $this->key])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            // Guzzle puts the full URL, key included, in its messages, so the
            // original exception is redacted rather than chained.
            $reason = str_replace($this->key, '[key]', $exception->getMessage());

            throw new MerriamWebsterFailed("Looking up \"{$term}\" failed: {$reason}");
        }

        // A rejected key still comes back 200, as a plain-text sentence.
        $decoded = json_decode($response->body(), true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new MerriamWebsterFailed("Merriam-Webster didn't answer \"{$term}\" with dictionary JSON: ".str($response->body())->limit(200));
        }

        return new Lookup($term, $decoded);
    }
}
