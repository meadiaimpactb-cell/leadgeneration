<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Root redirect (§12).
 *
 * Sends a visitor arriving at "/" to the right language version:
 * their remembered choice, else what their browser asks for, else Arabic.
 *
 * A 302, not a 301: the target depends on the visitor, so it must never be
 * cached by a browser or a CDN as a permanent rule for everyone.
 */
class LocaleRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $cookie = $request->cookie(config('site.locale_cookie'));

        $locale = Locales::negotiate(
            cookie: is_string($cookie) ? $cookie : null,
            acceptLanguage: $request->header('Accept-Language'),
        );

        // The `Vary: Accept-Language, Cookie` this response needs is applied by
        // the SetLocale middleware, not here — Inertia's middleware replaces
        // Vary on the way out and would discard anything set at this point.
        return redirect()->to("/{$locale}");
    }
}
