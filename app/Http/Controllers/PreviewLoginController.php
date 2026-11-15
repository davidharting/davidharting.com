<?php

namespace App\Http\Controllers;

use App\Http\LandingPage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Logs a reviewer into a PR preview as a seeded user and sends them to a page,
 * all from one link an agent can build without any secret.
 *
 * The route is only registered in PR previews (see routes/preview.php), whose
 * database holds nothing but seed data. The seeded `admin@example.com` /
 * `password` login is already public, so this link grants nothing a visitor
 * to a preview couldn't already get by typing it.
 */
class PreviewLoginController extends Controller
{
    /**
     * Only seeded users can be logged in as. Real accounts never use this domain.
     */
    private const string SEEDED_EMAIL_DOMAIN = '@example.com';

    private const string DEFAULT_EMAIL = 'admin@example.com';

    public function __invoke(Request $request): RedirectResponse
    {
        $email = $request->query('as', self::DEFAULT_EMAIL);

        abort_unless(is_string($email) && Str::endsWith(Str::lower($email), self::SEEDED_EMAIL_DOMAIN), 404);

        $user = User::query()->where('email', Str::lower($email))->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to($this->destination($request->query('to'), $user));
    }

    /**
     * A path on this site to land on, falling back to the user's landing page.
     *
     * Only site-relative paths are followed, so the link can't bounce the
     * reviewer to another host: `//evil.test` and `/\evil.test` are treated by
     * browsers as protocol-relative URLs and are refused.
     */
    private function destination(mixed $to, User $user): string
    {
        if (! is_string($to)
            || ! Str::startsWith($to, '/')
            || Str::startsWith($to, '//')
            || Str::contains($to, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $to) === 1) {
            return LandingPage::for($user);
        }

        return $to;
    }
}
