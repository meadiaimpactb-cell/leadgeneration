<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ProfileController;
use App\Models\LeadField;
use App\Models\User;
use App\Support\Brand;
use App\Support\Locales;
use App\Support\NavigationBuilder;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;
use Spatie\Permission\Models\Permission;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props shared with every page.
     *
     * Kept deliberately small — this payload is embedded in the HTML of every
     * response, so it counts against the page-weight budget (§15.1).
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return array_merge(parent::share($request), [
            'locale' => $locale,
            'dir' => Locales::dir($locale),
            'locales' => $this->localeSwitcher($request, $locale),
            'translations' => fn (): array => $this->translations($locale),
            'settings' => fn (): array => app(Settings::class)->public(),
            // The client's uploaded logo, icon and share image. Four short
            // strings; without them the panel could accept a logo it then had
            // no way to display (§19).
            'brand' => fn (): array => app(Brand::class)->urls(),
            'navigation' => fn (): array => app(NavigationBuilder::class)->all($locale),
            // The lead form's shape. Shared because the form appears in the
            // CTA band on nearly every page, so it must render identically
            // wherever it is (§6.1: one form only).
            'leadFields' => fn (): array => $this->leadFields(),
            /*
             * Whether the pages the landing page replaced are still switched
             * on. The admin sidebar hides their editors when they are not —
             * driven by the same `site.legacy_pages` flag the router reads, so
             * the menu and the site can never disagree about which pages
             * exist. A second list in the sidebar would be a second thing to
             * keep in step, which is how the old "القطاعات" entry survived
             * the screen it pointed at.
             */
            'legacyPages' => (bool) config('site.legacy_pages'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],

            // Only present for the admin panel. The public site has no
            // accounts at all, so this stays null there and costs nothing.
            'auth' => fn (): ?array => $this->auth($request),

            // The account workspace: who is signed in, the profile tabs, and
            // the settings screens they may open. Shared rather than returned
            // per controller because the column has to survive navigation —
            // every screen it links to renders inside the same frame, so every
            // one of them needs the same data.
            'workspace' => fn (): ?array => $this->workspace($request),
        ]);
    }

    /**
     * The enabled lead-form fields, in the order the client set.
     *
     * Cached because it is read on every page render and changes only when an
     * administrator edits the form.
     *
     * @return list<array<string, mixed>>
     */
    private function leadFields(): array
    {
        return Cache::remember(
            LeadField::cacheKey(app()->getLocale()),
            now()->addHour(),
            fn (): array => LeadField::query()
                ->enabled()
                ->with('translations')
                ->get()
                ->map(fn (LeadField $field): array => $field->toFormArray())
                ->all()
        );
    }

    /**
     * The signed-in operator and the permissions the panel's navigation reads.
     *
     * This drives which links are drawn — never whether a request is allowed.
     * That is a policy's job on the server (§9.2).
     *
     * @return array<string, mixed>|null
     */
    private function auth(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                // Shown in the panel bar in place of the initials.
                'avatar' => $user->avatarUrl(),
            ],
            // super-admin holds no permission rows — it passes through the
            // Gate::before rule instead — so listing its own permissions would
            // return an empty set and hide the entire navigation from it.
            'can' => ($user->hasRole(User::ROLE_SUPER_ADMIN)
                ? Permission::query()->pluck('name')
                : $user->getAllPermissions()->pluck('name')
            )
                ->mapWithKeys(fn (string $name): array => [$name => true])
                ->all(),
        ];
    }

    /**
     * Every enabled locale plus the URL of the current page in that locale,
     * so ui/LangSwitch can render real <a href> links (§7.2 — real links).
     *
     * @return list<array<string, mixed>>
     */
    private function localeSwitcher(Request $request, string $current): array
    {
        $path = $request->path();                      // e.g. "ar/solutions"
        $segments = explode('/', $path);
        $hasLocalePrefix = isset($segments[0]) && array_key_exists($segments[0], Locales::all());

        return collect(Locales::enabled())
            ->map(function (string $locale) use ($segments, $hasLocalePrefix, $current, $request): array {
                $rest = $hasLocalePrefix ? array_slice($segments, 1) : $segments;
                $target = trim($locale.'/'.implode('/', $rest), '/');

                return [
                    'code' => $locale,
                    'label' => Locales::all()[$locale]['native'],
                    'dir' => Locales::all()[$locale]['dir'],
                    'current' => $locale === $current,
                    'url' => url($target).($request->getQueryString() ? '?'.$request->getQueryString() : ''),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The standing account column, shared by every screen it links to.
     *
     * @return array<string, mixed>|null
     */
    private function workspace(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || ! $this->isAdminRequest()) {
            return null;
        }

        return [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
                'avatar' => $user->avatarUrl(),
                'cover' => $user->coverUrl(),
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                'createdAt' => $user->created_at?->toIso8601String(),
                // Stated rather than implied: §9.2 makes 2FA mandatory for
                // super-admin and it is not built, so the screen says so.
                'twoFactor' => false,
            ],
            'tabs' => array_map(
                fn (string $tab): array => [
                    'key' => $tab,
                    'href' => "/admin/profile/{$tab}",
                    'label' => __("admin.tab_{$tab}"),
                    'hint' => __("admin.tab_{$tab}_hint"),
                ],
                ProfileController::TABS,
            ),
            'shortcuts' => ProfileController::shortcutsFor($user),
        ];
    }

    /** Whether this request is for the admin panel rather than the public site. */
    private function isAdminRequest(): bool
    {
        return request()->is('admin', 'admin/*');
    }

    /**
     * Flatten resources/lang/{locale}/*.php into a single dot-keyed dictionary
     * for the client-side $t() helper.
     *
     * UI strings only. Site content never passes through here — it lives in
     * the translation tables (§12).
     *
     * @return array<string, string>
     */
    private function translations(string $locale): array
    {
        // Only the groups this side of the site actually renders.
        //
        // Every lang file used to ship on every response, so each public page
        // carried the entire admin panel's vocabulary — a couple of hundred
        // strings no visitor can ever see, in a payload embedded in the HTML
        // of every request (§15.1). It also meant a hint written for an editor
        // could turn up inside a public page's markup, which is how this was
        // noticed at all.
        $groups = $this->isAdminRequest()
            ? null                                   // the panel needs everything
            : ['common', 'nav', 'leads', 'contact', 'impact', 'training', 'products'];

        $path = lang_path($locale);

        if (! File::isDirectory($path)) {
            return [];
        }

        $messages = [];

        foreach (File::files($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $group = $file->getFilenameWithoutExtension();

            if ($groups !== null && ! in_array($group, $groups, true)) {
                continue;
            }

            $lines = require $file->getPathname();

            if (! is_array($lines)) {
                continue;
            }

            foreach (Arr::dot($lines) as $key => $value) {
                /*
                 * Arrays are skipped, not cast.
                 *
                 * `Arr::dot` flattens nested keys but leaves an EMPTY array as
                 * an array — and `validation.php` ships with `'custom' => []`.
                 * Casting that to string raised "Array to string conversion"
                 * and took the whole admin panel to a 500 the moment that file
                 * existed. A lang file is data written by hand; this loop has
                 * to survive whatever shape it is in.
                 */
                if (! is_scalar($value)) {
                    continue;
                }

                $messages["{$group}.{$key}"] = (string) $value;
            }
        }

        return $messages;
    }
}
