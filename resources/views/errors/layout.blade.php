{{--
    The error pages, in the site's own clothes.

    Laravel's stock pages were being served: an English "Not Found" on
    <html lang="en">, no logo, no icon, and the site's own colours nowhere in
    sight. On an Arabic site that reads as a different website entirely, which
    is the worst thing a 404 can do — a visitor who thinks they have left the
    site does not come back to it.

    Blade rather than an Inertia page on purpose. A 500 means something in the
    application has already failed, and a page that needs the framework to
    boot, resolve props and render Vue in order to say "something went wrong"
    is a page that will not render exactly when it is needed. This one is
    static markup, inline styles and no build step.
--}}
@php
    // Already resolved by errors/_locale, included by the view extending this.
    $locale = app()->getLocale();
    $rtl = App\Support\Locales::dir($locale) === 'rtl';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ __('errors.site') }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <meta name="theme-color" content="#002546">
    <meta name="robots" content="noindex">

    <style>
        /* The tokens this page needs, inlined — see the note above. */
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-block-size: 100dvh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #002546;
            color: #fff;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            line-height: 1.85;
            text-align: center;
        }
        .mark { inline-size: 56px; block-size: 56px; margin-block-end: 24px; }
        .code {
            font-size: 14px;
            letter-spacing: .18em;
            color: #DCAD75;
            margin: 0 0 8px;
        }
        h1 { font-size: clamp(1.5rem, 1.2rem + 1.4vw, 2.25rem); margin: 0 0 12px; }
        p { margin: 0 auto 32px; max-inline-size: 46ch; color: rgba(255,255,255,.78); }
        a.home {
            display: inline-flex;
            align-items: center;
            min-block-size: 44px;
            padding-inline: 28px;
            border-radius: 4px;
            background: #D7653B;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
        }
        a.home:hover { background: #B4522C; }
        /* The Sadu thread's divider, the one use that fits a page this bare. */
        .thread {
            inline-size: min(320px, 70vw);
            block-size: 8px;
            margin: 0 auto 28px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='8' viewBox='0 0 24 8'%3E%3Cpath d='M0 6.5 L6 1.5 L12 6.5 L18 1.5 L24 6.5' fill='none' stroke='%23DCAD75' stroke-width='1'/%3E%3C/svg%3E");
            background-repeat: repeat-x;
        }
    </style>
</head>
<body>
    <main>
        <img src="/favicon.svg" alt="" class="mark">
        <p class="code">@yield('code')</p>
        <h1>@yield('heading')</h1>
        <div class="thread" aria-hidden="true"></div>
        <p>@yield('body')</p>
        <a class="home" href="/{{ $locale }}">{{ __('errors.home') }}</a>
    </main>
</body>
</html>
