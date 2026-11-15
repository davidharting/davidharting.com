<?php

namespace App\Http\Controllers;

use App\Http\LandingPage;
use App\Models\User;
use App\Support\DeploymentInfo;
use App\Support\PreviewLoginLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logs a reviewer into a PR preview as a seeded user and sends them to a page,
 * from a signed link minted by PreviewLoginLink.
 *
 * The route is only registered in PR previews (see routes/preview.php).
 */
class PreviewLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $email = $request->query('as');
        $to = $request->query('to', '');
        $expires = $request->query('expires');
        $signature = $request->query('signature');

        abort_unless(
            is_string($email) && is_string($to) && is_string($expires) && ctype_digit($expires) && is_string($signature)
                && PreviewLoginLink::isValid((int) DeploymentInfo::pullRequestNumber(), $email, $to, (int) $expires, $signature),
            403,
            'This preview login link is invalid or has expired. Ask for a new one.',
        );

        $user = User::query()->where('email', $email)->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to($to !== '' ? $to : LandingPage::for($user));
    }
}
