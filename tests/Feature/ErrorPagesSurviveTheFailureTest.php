<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The error pages, held to the promise their own layout makes (§12, §15.3).
 *
 * `errors/layout.blade.php` explains why it is Blade and not an Inertia page:
 * a 500 means something has already failed, and a page that needs the
 * framework to boot, resolve props and render Vue in order to say «something
 * went wrong» is a page that will not render exactly when it is needed.
 *
 * That is a promise about dependencies, and nothing was holding it. These
 * tests do, by rendering each view with the database bound to a service that
 * throws the moment it is touched — the failure most likely to be the reason
 * the visitor is seeing a 500 in the first place. If someone later adds a
 * `Settings::get()` for the support phone number, or a menu, or anything else
 * that reaches for a row, this fails here rather than as a white screen on the
 * live site.
 *
 * Deliberately no RefreshDatabase: these pages must not need a database, and a
 * test that opens one would be assuming away the thing it is checking.
 */
class ErrorPagesSurviveTheFailureTest extends TestCase
{
    /**
     * Make any use of the database an immediate, obvious failure.
     *
     * The container binding is replaced rather than the connection purged
     * because a purged connection simply reconnects; this one cannot.
     */
    private function withNoDatabase(): void
    {
        DB::clearResolvedInstances();
        $this->app->forgetInstance('db');

        $this->app->bind('db', function (): never {
            throw new RuntimeException(
                'The error page reached for the database. It is served when the '
                .'application has already failed, so it must not depend on one.',
            );
        });
    }

    /** Put the visitor on a URL, so errors/_locale can read its first segment. */
    private function visiting(string $uri): void
    {
        $this->app->instance('request', Request::create($uri));
    }

    #[Test]
    public function every_error_page_renders_with_no_database_at_all(): void
    {
        $this->visiting('/ar/a-page-that-is-not-there');
        $this->withNoDatabase();

        foreach (['404', '500', '503'] as $code) {
            $html = view("errors.{$code}")->render();

            $this->assertStringContainsString($code, $html,
                "The {$code} page did not render its own code.");
            $this->assertStringContainsString('<html lang="ar" dir="rtl"', $html,
                "The {$code} page did not come out as an Arabic, right-to-left document.");
        }
    }

    /**
     * §12: an English visitor gets an English error. A 404 never matches the
     * locale-prefixed route group, so SetLocale never runs — the first URL
     * segment is the only thing left that knows which site they were reading.
     */
    #[Test]
    public function the_error_page_speaks_the_language_of_the_url_it_was_reached_from(): void
    {
        $this->visiting('/en/a-page-that-is-not-there');
        $this->withNoDatabase();

        $english = view('errors.404')->render();

        $this->assertStringContainsString('<html lang="en" dir="ltr"', $english);
        $this->assertStringContainsString(__('errors.404_heading', [], 'en'), $english);
    }

    /**
     * A URL with no locale prefix at all — the bare domain, or an old path
     * kept from before the prefixes existed. Arabic is the site's own default
     * and the right answer here.
     */
    #[Test]
    public function a_url_with_no_locale_prefix_falls_back_to_arabic(): void
    {
        $this->visiting('/some-legacy-path');
        $this->withNoDatabase();

        $html = view('errors.404')->render();

        $this->assertStringContainsString('<html lang="ar" dir="rtl"', $html);
    }

    /**
     * The pages carry no build output. A stylesheet fetched from the Vite
     * manifest is one more thing that can be missing during a bad deploy, and
     * it would take the error page down with it.
     */
    #[Test]
    public function the_error_pages_ask_for_nothing_the_build_produces(): void
    {
        $this->visiting('/ar/nowhere');
        $this->withNoDatabase();

        foreach (['404', '500', '503'] as $code) {
            $html = view("errors.{$code}")->render();

            $this->assertStringNotContainsString('/build/', $html,
                "The {$code} page links to build output, which a failed deploy may not have.");
            $this->assertStringNotContainsString('<script', $html,
                "The {$code} page runs script it does not need.");
        }
    }
}
