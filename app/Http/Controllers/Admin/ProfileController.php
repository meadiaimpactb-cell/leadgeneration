<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The signed-in operator's own account (§9.2, §19.3).
 *
 * Separate from the users screen, which is a super-admin tool for managing
 * other people. Everyone changes their own password without holding the
 * permission to change anyone else's — and the credentials handed over at
 * launch are meant to be replaced on first sign-in, which needs a screen the
 * recipient can actually reach.
 *
 * Laid out as a header plus a standing set of tabs, each its own URL. Tabs
 * rather than one long form because the sections have nothing to do with each
 * other: changing a password and changing a photograph should not share a
 * save button. Real URLs rather than client-side toggles so a tab can be
 * linked, bookmarked and returned to after a save.
 */
class ProfileController extends Controller
{
    /**
     * The tabs, in order. Only sections backed by something real appear —
     * a tab for a feature that does not exist is worse than its absence,
     * because it reads as broken rather than as pending.
     */
    public const TABS = ['details', 'appearance', 'password', 'security'];

    public function edit(Request $request, string $tab = 'details'): Response
    {
        if (! in_array($tab, self::TABS, true)) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();

        return Inertia::render('Admin/Profile', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->all(),
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                'createdAt' => $user->created_at?->toIso8601String(),
                'avatar' => $user->avatarUrl(),
                'cover' => $user->coverUrl(),
                // Stated plainly rather than implied by a green tick: §9.2
                // makes 2FA mandatory for super-admin and it is not built yet,
                // so the screen says "not enabled" instead of staying silent.
                'twoFactor' => false,
            ],
        ]);
    }

    /**
     * Screens reachable from the account column, filtered by what this
     * account may actually open.
     *
     * The panel is not the protection — every route is enforced by a policy
     * (§9.2) — but offering a link the server will refuse is a promise the
     * panel then breaks.
     *
     * @return list<array{key: string, icon: string, label: string, hint: string, href: string}>
     */
    public static function shortcutsFor(User $user): array
    {

        $items = [
            ['key' => 'brand', 'icon' => 'brand', 'href' => '/admin/brand',
                'can' => 'settings.manage'],
            ['key' => 'site', 'icon' => 'site', 'href' => '/admin/settings/site',
                'can' => 'settings.manage'],
            ['key' => 'contact', 'icon' => 'contact', 'href' => '/admin/settings/contact',
                'can' => 'settings.manage'],
            ['key' => 'tracking', 'icon' => 'tracking', 'href' => '/admin/settings/tracking',
                'can' => 'settings.manage'],
            ['key' => 'keywords', 'icon' => 'keywords', 'href' => '/admin/seo/keywords',
                'can' => 'pages.view'],
            ['key' => 'users', 'icon' => 'users', 'href' => '/admin/users',
                'can' => 'users.manage'],
        ];

        return collect($items)
            ->filter(fn (array $item): bool => $user->can($item['can']))
            ->map(fn (array $item): array => [
                'key' => $item['key'],
                'icon' => $item['icon'],
                'href' => $item['href'],
                'label' => __("admin.shortcut_{$item['key']}"),
                'hint' => __("admin.shortcut_{$item['key']}_hint"),
            ])
            ->values()
            ->all();
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->forceFill($data)->save();

        return back()->with('success', __('settings.saved'));
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            // Knowing the current password is what stops an unattended session
            // from being turned into permanent access (§15.3).
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()->symbols()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => __('admin.password_wrong')]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        return back()->with('success', __('admin.password_changed'));
    }

    /**
     * The operator's own photograph or cover.
     *
     * Scoped to `$request->user()` and never to an id from the payload: this
     * route is open to every role, so accepting a target would let a sales
     * operator replace the super-admin's picture.
     */
    public function image(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'collection' => ['required', 'string', 'in:avatar,cover'],
            // `mimes` checks the real type, not the filename (§9.2).
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
        ]);

        $user = $request->user();

        $user->mediaIn($data['collection'])?->delete();
        $user->addMedia($request->file('file'))->toMediaCollection($data['collection']);

        return back()->with('success', __('settings.saved'));
    }

    public function deleteImage(Request $request, string $collection): RedirectResponse
    {
        abort_unless(in_array($collection, ['avatar', 'cover'], true), 404);

        $request->user()->mediaIn($collection)?->delete();

        return back()->with('success', __('admin.deleted'));
    }
}
