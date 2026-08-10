<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\App;

/**
 * Locale helpers shared by middleware, controllers and the SEO builders.
 */
class Locales
{
    /** @return array<string, array<string, string>> */
    public static function all(): array
    {
        return config('site.locales');
    }

    /**
     * Locales actually served to the public.
     *
     * English is built in full from day one but its launch is gated behind a
     * setting so management can decide the timing without a deploy (§12).
     *
     * @return list<string>
     */
    public static function enabled(): array
    {
        $default = config('site.default_locale');

        if (! app(Settings::class)->bool('site.english_enabled', true)) {
            return [$default];
        }

        // The default locale is listed first deliberately: negotiate() walks
        // this list in order and keeps the first locale to reach the highest
        // weight, which is what makes Arabic win a tie. Do not sort this.
        return array_values(array_unique(
            array_merge([$default], array_keys(self::all()))
        ));
    }

    public static function isEnabled(string $locale): bool
    {
        return in_array($locale, self::enabled(), true);
    }

    public static function dir(?string $locale = null): string
    {
        return self::all()[$locale ?? App::getLocale()]['dir'] ?? 'rtl';
    }

    public static function isRtl(?string $locale = null): bool
    {
        return self::dir($locale) === 'rtl';
    }

    public static function htmlLang(?string $locale = null): string
    {
        return self::all()[$locale ?? App::getLocale()]['html_lang'] ?? 'ar';
    }

    /**
     * Resolve the best locale for a visitor arriving at the bare root (§12).
     *
     * The order of authority, strongest first:
     *
     *   1. **What the visitor chose.** A stored preference always wins. Once
     *      someone has picked a language, guessing again is a bug, not a
     *      feature.
     *   2. **What their browser asks for**, weighted by its own q-values.
     *   3. **Arabic.** The site is Arabic-first; English is served because the
     *      visitor's browser asked for it, never as a guess.
     *
     * Arabic deliberately wins a tie. A browser sending `ar` and `en` at equal
     * weight is telling us it is comfortable with both, and in that case the
     * site's own default decides.
     */
    public static function negotiate(?string $cookie, ?string $acceptLanguage): string
    {
        $enabled = self::enabled();
        $default = config('site.default_locale');

        if ($cookie !== null && in_array($cookie, $enabled, true)) {
            return $cookie;
        }

        $preferences = self::parseAcceptLanguage($acceptLanguage);

        $best = $default;
        $bestQ = 0.0;

        foreach ($enabled as $locale) {
            $q = $preferences[$locale] ?? 0.0;

            // Strictly greater, so the first locale to reach a given weight
            // keeps it. `enabled` is ordered with the default first, which is
            // what makes Arabic win ties.
            if ($q > $bestQ) {
                $best = $locale;
                $bestQ = $q;
            }
        }

        // No enabled locale was acceptable to this browser at all — a French-
        // only browser, or a crawler sending no header. Serve the default.
        return $bestQ > 0.0 ? $best : $default;
    }

    /**
     * Parse Accept-Language into a weight per base language.
     *
     * Handles what real browsers and crawlers actually send:
     *   · region subtags   — `en-GB` counts as `en`
     *   · quality values   — `ar;q=0.8`
     *   · explicit refusal — `q=0` means "not acceptable", not "acceptable"
     *   · the wildcard     — `*` matches anything we serve, at its own weight
     *
     * @return array<string, float> base language => highest weight requested
     */
    private static function parseAcceptLanguage(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        $weights = [];
        $wildcard = null;

        foreach (explode(',', $header) as $part) {
            $bits = explode(';q=', trim($part));
            $tag = strtolower(trim($bits[0]));

            if ($tag === '') {
                continue;
            }

            $q = isset($bits[1]) ? (float) $bits[1] : 1.0;

            // RFC 9110: q=0 means the client refuses this language.
            if ($q <= 0.0) {
                continue;
            }

            if ($tag === '*') {
                $wildcard = max($wildcard ?? 0.0, $q);

                continue;
            }

            // "en-GB" and "en-US" are both English to us.
            $base = substr($tag, 0, 2);

            $weights[$base] = max($weights[$base] ?? 0.0, $q);
        }

        if ($wildcard !== null) {
            foreach (self::enabled() as $locale) {
                $weights[$locale] = max($weights[$locale] ?? 0.0, $wildcard);
            }
        }

        return $weights;
    }
}
