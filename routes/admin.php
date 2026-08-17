<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CrmController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LeadFieldController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PageKeywordController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SectionMediaController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SitemapController;
use App\Http\Controllers\Admin\UpcomingScreenController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel (§9)
|--------------------------------------------------------------------------
| A custom panel on the same stack as the public site — no Filament, no Nova
| (§9). Arabic RTL by default. Not locale-prefixed: the panel's own interface
| language is a per-user preference, separate from the content locale being
| edited.
|
| Authorisation is enforced by policies on every resource, never by hiding
| buttons (§9.2).
*/

Route::prefix('admin')->name('admin.')->group(function (): void {

    // ---- Guest ----------------------------------------------------------
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AuthController::class, 'create'])->name('login');
        Route::post('login', [AuthController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::post('logout', [AuthController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    // ---- Authenticated ---------------------------------------------------
    Route::middleware(['auth', 'admin.active'])->group(function (): void {

        Route::get('/', DashboardController::class)->name('dashboard');

        // ---- Leads (§11.4) ----------------------------------------------
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/export', [LeadController::class, 'export'])->name('leads.export');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::patch('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::post('leads/{lead}/resync', [LeadController::class, 'resync'])->name('leads.resync');
        /*
         * Archive, and POST rather than DELETE — the verb is the point.
         *
         * `AdminAccessTest::a_lead_can_never_be_deleted_through_the_panel`
         * asserts that no route under `admin/leads` answers DELETE, and that
         * assertion must keep passing. Nothing here deletes: the row is
         * stamped and hidden from one list. Using DELETE would have been both
         * a lie about what happens and a broken guard.
         */
        Route::post('leads/{lead}/archive', [LeadController::class, 'archive'])->name('leads.archive');

        // ---- Pages + the section builder (§9.1) --------------------------
        Route::resource('pages', PageController::class)->except(['show']);
        Route::post('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');

        /*
         * Which library images a section uses, and in what order.
         *
         * Registered BEFORE `sections/{type}/{id}` on purpose. Both are three
         * segments, so `POST sections/5/media` would otherwise be read as
         * "create a section on owner type 5 with id media" and land in the
         * wrong controller. Matching this one first is unambiguous in the
         * other direction: a real store call is `sections/page/12`, whose
         * third segment is never the literal word `media`.
         */
        Route::post('sections/{section}/media', [SectionMediaController::class, 'sync'])
            ->whereNumber('section')->name('sections.media.sync');
        Route::delete('sections/{section}/media/{medium}', [SectionMediaController::class, 'detach'])
            ->whereNumber('section')->name('sections.media.detach');

        // Sections attach polymorphically to pages, solutions, sectors and
        // campaigns, so they are addressed by owner type + id.
        Route::get('sections/{type}/{id}', [SectionController::class, 'index'])->name('sections.index');
        Route::post('sections/{type}/{id}', [SectionController::class, 'store'])->name('sections.store');
        Route::patch('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
        Route::post('sections/{type}/{id}/reorder', [SectionController::class, 'reorder'])->name('sections.reorder');

        // ---- Campaigns (§9.1 wizard) -------------------------------------
        Route::resource('campaigns', CampaignController::class)->except(['show']);

        // ---- Content entities --------------------------------------------
        // One controller for the entities that share the same shape:
        // translated fields + media + ordering (§8.2).
        Route::get('content/{entity}', [ResourceController::class, 'index'])->name('content.index');
        Route::get('content/{entity}/create', [ResourceController::class, 'create'])->name('content.create');
        Route::post('content/{entity}', [ResourceController::class, 'store'])->name('content.store');
        Route::get('content/{entity}/{id}/edit', [ResourceController::class, 'edit'])->name('content.edit');
        Route::patch('content/{entity}/{id}', [ResourceController::class, 'update'])->name('content.update');
        Route::delete('content/{entity}/{id}', [ResourceController::class, 'destroy'])->name('content.destroy');
        Route::post('content/{entity}/reorder', [ResourceController::class, 'reorder'])->name('content.reorder');

        // Which library images a content record uses. This is where a
        // partner's logo and an artisan's portrait are chosen — the logo strip
        // and the story carousel render those tables, not section media.
        Route::post('content/{entity}/{id}/media', [ResourceController::class, 'media'])
            ->whereNumber('id')->name('content.media');

        // ---- Media -------------------------------------------------------
        // The library reads and writes as JSON: the same list is browsed from
        // inside the picker modal, from the content screens and from the media
        // screen, so it cannot be one page's Inertia prop.
        // Declared before `media/{medium}` so `library` is never read as an id.
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/library', [MediaController::class, 'library'])->name('media.library');
        Route::post('media/library', [MediaController::class, 'upload'])->name('media.upload');
        Route::get('media/{medium}/usage', [MediaController::class, 'usage'])->name('media.usage');

        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::patch('media/{medium}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('media/{medium}', [MediaController::class, 'destroy'])->name('media.destroy');

        // ---- Menus, settings, users --------------------------------------
        Route::get('navigation', [NavigationController::class, 'index'])->name('navigation.index');
        Route::put('navigation/{navigation}', [NavigationController::class, 'update'])->name('navigation.update');

        // The lead form builder (§6.1) — turn fields on/off, reorder, rename.
        Route::get('lead-fields', [LeadFieldController::class, 'index'])->name('lead-fields.index');
        Route::put('lead-fields', [LeadFieldController::class, 'update'])->name('lead-fields.update');

        // Managed 301s (§13) — protects the indexing of legacy URLs.
        Route::get('redirects', [RedirectController::class, 'index'])->name('redirects.index');
        Route::put('redirects', [RedirectController::class, 'update'])->name('redirects.update');

        // Settings are split one screen per concern (§9.1). The bare path
        // lands on the first screen so an old bookmark still works.
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::get('settings/{screen}', [SettingController::class, 'index'])->name('settings.screen');

        /*
         * Keywords — one screen, at the obvious address.
         *
         * There were two: an open site-wide list, and this per-page analysis
         * beside it. In practice that meant entering the same phrases twice
         * and learning which screen answered which question, and the people
         * who will be trained on this panel are not SEO staff. The per-page
         * screen is the one kept, because its answer is a list of edits rather
         * than a list of words.
         *
         * The old `seo/page-keywords` path redirects rather than 404s: it is
         * in browser histories and in the link on the site-wide screen that
         * used to point here.
         */
        Route::get('seo/keywords', [PageKeywordController::class, 'index'])->name('seo.keywords');
        Route::post('seo/keywords', [PageKeywordController::class, 'store'])->name('seo.keywords.store');
        Route::post('seo/keywords/reanalyse', [PageKeywordController::class, 'reanalyse'])->name('seo.keywords.reanalyse');
        Route::put('seo/keywords/{pageKeyword}/primary', [PageKeywordController::class, 'primary'])->name('seo.keywords.primary');
        Route::delete('seo/keywords/{pageKeyword}', [PageKeywordController::class, 'destroy'])->name('seo.keywords.destroy');

        // Absolute: the group already prefixes `admin`, and a relative target
        // here produced /admin/seo/admin/seo/keywords.
        Route::redirect('seo/page-keywords', '/admin/seo/keywords');

        // Everyone manages their own account, whatever their role — the
        // credentials handed over at launch (§19.3) are meant to be replaced
        // on first sign-in.
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
        Route::post('profile/image', [ProfileController::class, 'image'])->name('profile.image');
        Route::delete('profile/image/{collection}', [ProfileController::class, 'deleteImage'])
            ->name('profile.image.destroy');
        // Each tab is its own URL, so it can be linked and returned to.
        Route::get('profile/{tab}', [ProfileController::class, 'edit'])->name('profile.tab');

        // The client's own brand assets (§10, §19).
        Route::get('brand', [BrandController::class, 'edit'])->name('brand.edit');
        Route::post('brand', [BrandController::class, 'store'])->name('brand.store');
        Route::delete('brand/{medium}', [BrandController::class, 'destroy'])->name('brand.destroy');

        Route::resource('users', UserController::class)->except(['show']);

        /*
         * Screens whose place in the panel is agreed but whose function is
         * built in a later phase. Real routes, so no sidebar entry ever
         * 404s — see UpcomingScreenController for the list and the phase
         * each one belongs to.
         */
        // 2.2 — the CRM connection (§6.3). Configures the driver abstraction;
        // never talks to a provider directly (§22.8).
        Route::get('integrations/crm', [CrmController::class, 'index'])->name('crm.index');
        Route::put('integrations/crm', [CrmController::class, 'update'])->name('crm.update');
        Route::post('integrations/crm/test', [CrmController::class, 'test'])->name('crm.test');
        Route::post('integrations/crm/resync-all', [CrmController::class, 'resyncAll'])->name('crm.resync-all');

        // 2.1 — the English site's switch and its translation coverage (§12).
        Route::get('languages', [LanguageController::class, 'index'])->name('languages.index');
        Route::put('languages', [LanguageController::class, 'update'])->name('languages.update');

        Route::get('backups', [UpcomingScreenController::class, 'show'])->defaults('screen', 'backups')->name('upcoming.backups');

        // The audit trail (§9.1). Read only — there is deliberately no route
        // here that can alter or remove a row; see the controller.
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
        // What the crawler is currently offered, read from the generator that
        // serves it (§13).
        Route::get('seo/sitemap', [SitemapController::class, 'index'])->name('seo.sitemap');

        // The sender's confirmation is a real settings screen now: its wording
        // is the client's to write, and until they do nothing is sent (§6.2).
        Route::get('integrations/confirmations', [SettingController::class, 'index'])
            ->defaults('screen', 'confirmations')->name('integrations.confirmations');

        /*
         * Who is told when an enquiry arrives — the panel's answer to §20
         * decision 4, which until now could only be changed by editing the
         * server's .env.
         */
        Route::get('integrations/notifications', [NotificationController::class, 'index'])
            ->name('integrations.notifications');
        Route::post('integrations/notifications', [NotificationController::class, 'store'])
            ->name('integrations.notifications.store');
        Route::put('integrations/notifications', [NotificationController::class, 'update'])
            ->name('integrations.notifications.update');
        Route::delete('integrations/notifications/{recipient}', [NotificationController::class, 'destroy'])
            ->name('integrations.notifications.destroy');

        Route::get('integrations/{screen}', [UpcomingScreenController::class, 'show'])
            ->whereIn('screen', ['spam'])
            ->name('upcoming.integrations');
    });
});
