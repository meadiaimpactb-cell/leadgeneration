<?php

declare(strict_types=1);

use App\Http\Middleware\ApplyRedirects;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // GLOBAL, not in the web group. A legacy URL matches no route at all,
        // so it never reaches route middleware — the 404 is produced during
        // routing. Only a global middleware sees that response and can turn it
        // into the managed 301 the client configured (§13, §22.7).
        $middleware->prepend(ApplyRedirects::class);

        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'admin.active' => EnsureAdminIsActive::class,
        ]);

        // Guests hitting the panel go to the admin login. The public site has
        // no accounts at all, so there is no other login to redirect to.
        $middleware->redirectGuestsTo(fn () => route('admin.login'));

        // Trust Laragon / the production reverse proxy so rate limiting and
        // ip_hash see the real client address rather than the proxy's.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Report unhandled exceptions to Sentry — when, and only when, a DSN
         * is configured.
         *
         * With `SENTRY_LARAVEL_DSN` empty the integration is inert: nothing is
         * captured, nothing is transmitted, and development is unaffected. That
         * is the state the repository ships in, deliberately. Sending error
         * payloads to a third party is a decision about where this project's
         * data goes, and it belongs to Amad Craft, not to a default.
         *
         * On the live site the alternative is what the panel has now: a 500
         * reaches the visitor, `APP_DEBUG=false` correctly hides the trace, and
         * the only record is a line in `storage/logs/laravel.log` that nobody
         * is watching. Two real 500s this week were found by screenshot.
         */
        Integration::handles($exceptions);
    })
    ->create()
    // §7.3 places UI strings under resources/lang/{ar,en}.
    ->useLangPath(dirname(__DIR__).'/resources/lang');
