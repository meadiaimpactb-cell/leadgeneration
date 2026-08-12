@php
    use App\Support\Locales;

    $locale = app()->getLocale();
    $dir = Locales::dir($locale);
    $settings = app(App\Support\Settings::class);
@endphp
<!DOCTYPE html>
<html lang="{{ Locales::htmlLang($locale) }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Staging must never be indexed (§16). --}}
    @if (app()->environment(['local', 'staging']))
        <meta name="robots" content="noindex, nofollow">
    @endif

    {{--
        One preloaded font only (§15.1). The Arabic display face is what the
        hero headline renders in, so it is the one that would otherwise cause
        a visible swap on the largest text on the page.
    --}}
    <link rel="preload" href="/fonts/SaudiWeb-Bold.woff2" as="font" type="font/woff2" crossorigin>

    {{--
        The browser icon.

        An upload from the panel wins, as it always did. Without one the site
        ships its own set, generated from the identity by
        scripts/make-favicons.php — the Sadu weave rather than the lockup,
        because «أمد الحرف» over «Amad Craft» is four illegible smudges at
        16×16 and the weave is what survives that size (§10.1).

        The .ico is listed even though the SVG is better everywhere it works:
        a browser that finds no <link> asks for /favicon.ico by name, and the
        one in this repo was a zero-byte file — which is what put a blank
        square in the tab. Order matters below: SVG first for anything modern,
        .ico last as the floor.

        This block sits in the one shared template, so the public site in both
        languages, the panel, the sign-in screen and every error page inherit
        it without repeating it.
    --}}
    @if ($favicon = app(App\Support\Brand::class)->url('favicon'))
        <link rel="icon" href="{{ $favicon }}">
    @else
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
        <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
    @endif

    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#002546">

    {{-- Head tags rendered by Inertia (title, description, canonical, hreflang, schema). --}}
    @inertiaHead

    {{--
        Google Search Console ownership verification (§14.1). The client pastes
        only the content value from Search Console's HTML-tag method; the tag
        itself is built here so they cannot paste a malformed one.
    --}}
    @if ($verification = $settings->get('tracking.search_console'))
        <meta name="google-site-verification" content="{{ $verification }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{--
        Google Tag Manager. The container ID is a client-managed setting, never
        a constant in code (§14.1, §22.9). Absent ID = no tag, no console error.
    --}}
    @if ($gtm = $settings->get('tracking.gtm_id'))
        <script>
            window.dataLayer = window.dataLayer || [];
        </script>
        <script async src="https://www.googletagmanager.com/gtm.js?id={{ $gtm }}"></script>
    @endif
</head>
<body>
    {{-- Keyboard users reach the content without tabbing the whole header (§10.8). --}}
    <a class="skip-link" href="#main">{{ __('common.skip_to_content') }}</a>

    @if ($gtm = $settings->get('tracking.gtm_id'))
        <noscript>
            <iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}"
                    height="0" width="0" style="display:none;visibility:hidden"></iframe>
        </noscript>
    @endif

    @inertia
</body>
</html>
