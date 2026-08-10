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

    {{-- The browser icon the client uploaded, falling back to the identity
         file shipped with the build. --}}
    @if ($favicon = app(App\Support\Brand::class)->url('favicon'))
        <link rel="icon" href="{{ $favicon }}">
    @else
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @endif

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
