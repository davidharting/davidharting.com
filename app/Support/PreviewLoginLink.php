<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Signed one-tap login links for PR previews.
 *
 * A link logs David into one PR's preview as a seeded user and opens a page
 * there. Production mints links (it holds PREVIEW_LOGIN_KEY but never
 * registers the login route) and previews check them with the same key.
 *
 * The signature covers the PR number, the user, the destination and the
 * expiry, so a link can't be edited, reused on another PR's preview, or used
 * after it expires.
 */
final readonly class PreviewLoginLink
{
    public const string DEFAULT_EMAIL = 'admin@example.com';

    public const int LIFETIME_HOURS = 24;

    /**
     * Only seeded users can be logged in as. Real accounts never use this domain.
     */
    private const string SEEDED_EMAIL_DOMAIN = '@example.com';

    private function __construct(
        public string $url,
        public CarbonImmutable $expiresAt,
    ) {}

    /**
     * Mint a link that logs into the given PR's preview as $email and opens
     * $to, or the user's landing page when $to is empty.
     *
     * @throws InvalidArgumentException when $pullRequest isn't a PR number, $email isn't a seeded user or $to isn't a path on the site
     * @throws RuntimeException when PREVIEW_LOGIN_KEY isn't set
     */
    public static function mint(int $pullRequest, string $email = self::DEFAULT_EMAIL, string $to = ''): self
    {
        $email = strtolower($email);

        if ($pullRequest < 1) {
            throw new InvalidArgumentException("[{$pullRequest}] is not a pull request number.");
        }

        if (! self::isSeededEmail($email)) {
            throw new InvalidArgumentException("Only seeded @example.com users can be logged in as, not [{$email}].");
        }

        if (! self::isSafeDestination($to)) {
            throw new InvalidArgumentException("The destination must be a path on the site starting with a single /, not [{$to}].");
        }

        $key = self::key() ?? throw new RuntimeException('PREVIEW_LOGIN_KEY is not set, so preview login links cannot be signed.');
        $expiresAt = CarbonImmutable::now()->addHours(self::LIFETIME_HOURS)->startOfSecond();
        $expires = $expiresAt->getTimestamp();

        $query = array_filter([
            'as' => $email,
            'to' => $to,
            'expires' => $expires,
            'signature' => self::sign($key, $pullRequest, $email, $to, $expires),
        ], fn (string|int $value): bool => $value !== '');

        return new self(
            self::previewUrl($pullRequest).'/preview-login?'.http_build_query($query, encoding_type: PHP_QUERY_RFC3986),
            $expiresAt,
        );
    }

    /**
     * Whether a link's parameters were signed for this PR and haven't expired.
     */
    public static function isValid(int $pullRequest, string $email, string $to, int $expires, string $signature): bool
    {
        $key = self::key();

        return $key !== null
            && $expires > CarbonImmutable::now()->getTimestamp()
            && self::isSeededEmail($email)
            && self::isSafeDestination($to)
            && hash_equals(self::sign($key, $pullRequest, $email, $to, $expires), $signature);
    }

    /**
     * The address of a PR's preview web service. Render names every preview
     * after its parent service with a `-pr-<number>` suffix.
     */
    private static function previewUrl(int $pullRequest): string
    {
        return "https://davidhartingdotcom-web-pr-{$pullRequest}.onrender.com";
    }

    private static function isSeededEmail(string $email): bool
    {
        return str_ends_with($email, self::SEEDED_EMAIL_DOMAIN);
    }

    /**
     * Empty (meaning the user's landing page) or a path on this site.
     *
     * `//evil.test` and `/\evil.test` are read by browsers as links to another
     * host, so they are refused along with absolute URLs. Whitespace and
     * control characters are refused too: a path should arrive percent-encoded.
     */
    private static function isSafeDestination(string $to): bool
    {
        if ($to === '') {
            return true;
        }

        return str_starts_with($to, '/')
            && ! str_starts_with($to, '//')
            && ! str_contains($to, '\\')
            && preg_match('/[\x00-\x20\x7F]/', $to) !== 1;
    }

    private static function sign(string $key, int $pullRequest, string $email, string $to, int $expires): string
    {
        return hash_hmac('sha256', implode("\n", [$pullRequest, $email, $to, $expires]), $key);
    }

    private static function key(): ?string
    {
        $key = config('services.preview_login.key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
