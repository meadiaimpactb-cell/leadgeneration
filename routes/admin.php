<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KeywordController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LeadFieldController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SettingController;
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

        // ---- Pages + the section builder (§9.1) --------------------------
        Route::resource('pages', PageController::class)->except(['show']);
        Route::post('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');

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

        // ---- Media -------------------------------------------------------
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

        // Target keywords — one open list, no page to pick first (§13).
        Route::get('seo/keywords', [KeywordController::class, 'index'])->name('seo.keywords');
        Route::post('seo/keywords', [KeywordController::class, 'store'])->name('seo.keywords.store');
        Route::put('seo/keywords/{keyword}', [KeywordController::class, 'update'])->name('seo.keywords.update');
        Route::delete('seo/keywords/{keyword}', [KeywordController::class, 'destroy'])->name('seo.keywords.destroy');

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
        Route::get('media', [UpcomingScreenController::class, 'show'])->defaults('screen', 'media')->name('upcoming.media');
        Route::get('languages', [UpcomingScreenController::class, 'show'])->defaults('screen', 'languages')->name('upcoming.languages');
        Route::get('backups', [UpcomingScreenController::class, 'show'])->defaults('screen', 'backups')->name('upcoming.backups');
        Route::get('activity', [UpcomingScreenController::class, 'show'])->defaults('screen', 'activity')->name('upcoming.activity');
        Route::get('seo/sitemap', [UpcomingScreenController::class, 'show'])->defaults('screen', 'sitemap')->name('upcoming.sitemap');

        Route::get('integrations/{screen}', [UpcomingScreenController::class, 'show'])
            ->whereIn('screen', ['crm', 'notifications', 'confirmations', 'spam'])
            ->name('upcoming.integrations');
    });
});
