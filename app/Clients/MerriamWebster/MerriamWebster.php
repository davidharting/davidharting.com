<?php

namespace App\Clients\MerriamWebster;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Looks words up in Merriam-Webster's Collegiate Dictionary API.
 *
 * The free non-commercial key allows 1,000 queries a day, so every answer,
 * including "not found", is cached for a year. Failures are never cached.
 * Connection errors, 429s and 5xx responses are retried twice with backoff;
 * other errors won't get better on a retry, so they fail at once.
 *
 * @see https://dictionaryapi.com/products/api-collegiate-dictionary
 */
class MerriamWebster
{
    private const string BASE_URL = 'https://www.dictionaryapi.com/api/v3/references/collegiate/json';

    /** @var list<int> milliseconds to wait before each retry */
    private const array RETRY_BACKOFF = [200, 1000];

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

        // The cache holds M-W's decoded JSON, not the Lookup built from it, so
        // a change to Entry never leaves stale objects behind.
        $cached = Cache::get(self::cacheKey($term));

        if (is_array($cached)) {
            return Lookup::fromResponse($term, $cached);
        }

        // fetch() throws on anything it can't read, so only good answers are cached.
        $lookup = $this->fetch($term);
        Cache::put(self::cacheKey($term), $lookup->response, now()->addYear());

        return $lookup;
    }

    /**
     * Hashed, so a long term can't overflow the database cache's key column.
     */
    private static function cacheKey(string $term): string
    {
        return 'merriam-webster:collegiate:'.hash('xxh128', $term);
    }

    /**
     * @throws MerriamWebsterFailed
     */
    private function fetch(string $term): Lookup
    {
        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(self::RETRY_BACKOFF, when: self::isWorthRetrying(...))
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

        return Lookup::fromResponse($term, $decoded);
    }

    private static function isWorthRetrying(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response->status() === 429 || $exception->response->serverError());
    }
}
