<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active locale from the {locale} route prefix, and remembers it.
 *
 * Every public URL carries its locale (/ar/..., /en/...) — see §12. The bare
 * root is negotiated by LocaleRedirectController, not here, so this middleware
 * stays a pure "read the prefix, set the locale, remember it" step.
 *
 * Remembering is the half that was missing: §12 requires the preference to be
 * stored in a cookie, and without writing it the site re-guessed on every
 * visit and quietly overrode what the visitor had chosen.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (! is_string($locale) || ! array_key_exists($locale, config('site.locales'))) {
            $locale = config('site.default_locale');
        }

        app()->setLocale($locale);

        $response = $next($request);

        $this->remember($request, $locale);
        $this->varyOnLocaleSignals($request, $response);

        return $response;
    }

    /**
     * Tell caches that "/" genuinely differs per visitor.
     *
     * This lives here rather than in LocaleRedirectController because
     * Inertia's middleware *replaces* Vary with `X-Inertia` on the way out,
     * and it unwinds after the controller — so a header set there is silently
     * discarded. SetLocale is the outermost of our middleware, so it unwinds
     * last and its value survives. Merged, never replaced, so X-Inertia stays.
     */
    private function varyOnLocaleSignals(Request $request, Response $response): void
    {
        // Only the bare root negotiates. Every other public URL names its
        // locale, so varying them would fragment the cache for no benefit.
        if ($request->path() !== '/') {
            return;
        }

        $existing = array_filter(array_map(
            'trim',
            explode(',', (string) $response->headers->get('Vary'))
        ));

        $merged = array_unique(array_merge($existing, ['Accept-Language', 'Cookie']));

        $response->headers->set('Vary', implode(', ', $merged));
    }

    /**
     * Store the locale the visitor is actually reading.
     *
     * Viewing a page IS the choice — whether they arrived by clicking the
     * language switch or by landing on that version from a search result. The
     * cookie is only re-sent when it changes, so a returning visitor does not
     * collect a Set-Cookie header on every request.
     */
    private function remember(Request $request, string $locale): void
    {
        // Only public locale-prefixed pages express a language preference. A
        // POST, a redirect or an asset says nothing about what to read next.
        if (! $request->isMethod('GET') || $request->route('locale') === null) {
            return;
        }

        $name = config('site.locale_cookie');

        if ($request->cookie($name) === $locale) {
            return;
        }

        Cookie::queue(
            $name,
            $locale,
            (int) config('site.locale_cookie_days', 365) * 24 * 60,
            '/',
            null,
            $request->isSecure(),
            true,          // httpOnly — no script needs to read this
            false,
            'Lax',         // survives a normal click-through from search
        );
    }
}
