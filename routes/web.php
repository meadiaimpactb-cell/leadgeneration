<?php

declare(strict_types=1);

use App\Http\Controllers\Public\CampaignController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\ImpactController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\LeadController;
use App\Http\Controllers\Public\LocaleRedirectController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PartnerController;
use App\Http\Controllers\Public\ProductController;
use App\Http\Controllers\Public\SectorController;
use App\Http\Controllers\Public\SeoFileController;
use App\Http\Controllers\Public\SolutionController;
use App\Http\Controllers\Public\TrainingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes (§5)
|--------------------------------------------------------------------------
| Every public URL carries its locale prefix (§12). Real routes with real
| HTTP statuses — no client-side-only routing — because indexing depends on
| it (§7.2).
*/

// Bare root: negotiate a locale and redirect. It never renders content, so
// there is no duplicate copy of the home page at "/" (§13).
Route::get('/', LocaleRedirectController::class)->name('root');

/*
| Crawler files (§13). Deliberately declared before the {locale} group: that
| group matches a single segment, and without these first "sitemap.xml" would
| be tested as a locale before ever reaching here.
|
| Not locale-prefixed — a crawler looks for them at the root, and the index
| points at one sitemap per language.
*/
Route::get('/robots.txt', [SeoFileController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoFileController::class, 'index'])->name('sitemap');
Route::get('/sitemap-{locale}.xml', [SeoFileController::class, 'locale'])
    ->whereIn('locale', array_keys(config('site.locales')))
    ->name('sitemap.locale');

Route::prefix('{locale}')
    ->whereIn('locale', array_keys(config('site.locales')))
    ->group(function (): void {
        /*
         * The single landing page (management decision, 7 September 2026).
         *
         * The route name stays `home`: it is referenced by the sitemap, the
         * navigation table, the footer and a number of tests, and renaming it
         * would break all of them to no purpose. What answers here changed;
         * where it lives did not (§22.7).
         */
        Route::get('/', [LandingController::class, 'index'])->name('home');

        /*
         * The eleven pages the landing-page decision retired (7 Sept 2026).
         *
         * Registered only while `site.legacy_pages` is on. With it off — the
         * default, and production — none of these paths resolve, so each
         * returns 404 and `ApplyRedirects` carries it 301 to its anchor on the
         * landing page from the `redirects` table (§22.7).
         *
         * Switched off rather than deleted: the controllers, the Vue pages and
         * their tests are all still here and still exercised with the flag on,
         * so restoring any of these pages is one line in `.env` rather than a
         * recovery from git. Delete them when Amad Craft says they are not
         * coming back.
         */
        if (config('site.legacy_pages')) {
            Route::get('/about', [PageController::class, 'about'])->name('about');

            Route::get('/solutions', [SolutionController::class, 'index'])->name('solutions.index');

            /*
             * The four audience segments, addressed as solutions.
             *
             * They are Sector records served by SectorController — only the URL
             * moved, because "is there something here for a body like mine" is
             * what a buyer opens a Solutions menu to answer.
             *
             * Registered as four LITERAL paths, not as one `{slug}` route with a
             * whereIn constraint. Laravel's route collection is keyed by method
             * and URI, so a second `/solutions/{slug}` route silently replaces
             * the first however it is constrained — which is exactly what
             * happened: the segments all returned 404 while the solutions below
             * kept working. Literal URIs are distinct keys, so both live.
             */
            foreach (['government', 'companies', 'partners', 'artisans'] as $segment) {
                Route::get("/solutions/{$segment}", [SectorController::class, 'show'])
                    ->defaults('slug', $segment)
                    ->name("sectors.{$segment}");
            }

            Route::get('/solutions/{slug}', [SolutionController::class, 'show'])->name('solutions.show');

            Route::get('/products', [ProductController::class, 'index'])->name('products.index');
            Route::get('/impact', [ImpactController::class, 'index'])->name('impact.index');
            /*
             * Literal before wildcard. `/impact/stories` and `/impact/stories/{slug}`
             * are distinct routes rather than one optional parameter, for the same
             * reason the four solution segments are four literal routes: Laravel
             * keys its route collection by method+URI, and a second registration on
             * one URI silently replaces the first.
             */
            Route::get('/impact/stories', [ImpactController::class, 'stories'])->name('impact.stories');
            Route::get('/impact/stories/{slug}', [ImpactController::class, 'story'])->name('impact.story');
            Route::get('/training', [TrainingController::class, 'index'])->name('training.index');
            Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
            Route::get('/contact', [ContactController::class, 'index'])->name('contact.index');
        }

        // Campaign landing pages — no navigation, one goal (§11.3).
        Route::get('/c/{slug}', [CampaignController::class, 'show'])->name('campaigns.show');

        Route::get('/legal/{slug}', [PageController::class, 'legal'])->name('legal.show');

        /*
         * The route that makes "add a page with no technical help" real (§9.1).
         *
         * Every public URL above is a literal path bound to a controller that
         * carries its own datasets. A page created in the panel has no such
         * path, so it was published, translated and indexable while its
         * address answered 404 — and `SitemapGenerator::reachable()` had to
         * grow a guard to stop the sitemap advertising it. This is the other
         * half of that fix: the URL now resolves, so the guard passes it.
         *
         * Registered LAST on purpose. Laravel matches in registration order,
         * so every literal path above still wins and nothing that resolves
         * today changes hands. Unknown slugs, drafts and pages untranslated in
         * this locale all still 404 — from the controller now rather than the
         * router, at the same status.
         */
        Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
    });

/*
|--------------------------------------------------------------------------
| Lead capture (§6)
|--------------------------------------------------------------------------
| One endpoint, throttled per IP (§15.3). Deliberately not locale-prefixed:
| the form posts from every page and the locale travels with the payload.
*/
Route::post('/leads', [LeadController::class, 'store'])
    ->middleware('throttle:leads')
    ->name('leads.store');

require __DIR__.'/admin.php';
