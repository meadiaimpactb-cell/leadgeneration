<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Seo\RobotsBuilder;
use App\Support\Settings;
use App\Support\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Site settings (§14.1), one screen per concern.
 *
 * This was a single page rendering the settings table verbatim: thirty rows
 * labelled with their database keys, in alphabetical order, contact details
 * sitting next to tracking tokens. §9.1 asks for a panel usable "with no
 * technical help needed", and that page failed the moment someone had to
 * guess what `dock_note.ar` was.
 *
 * Splitting it does three things: each screen answers one question, the
 * sidebar becomes a map of what can be changed, and every field can carry a
 * name and a sentence of explanation without the page becoming a wall.
 */
class SettingController extends Controller
{
    public function index(Request $request, string $screen = 'contact'): Response
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        if (! SettingsRegistry::isScreen($screen)) {
            throw new NotFoundHttpException;
        }

        $fields = Setting::query()
            ->get()
            ->map(function (Setting $setting): array {
                $key = "{$setting->group}.{$setting->key}";
                $meta = SettingsRegistry::describe($key);
                $hint = SettingsRegistry::hints()[$key] ?? [];

                return [
                    'placeholder' => $hint['placeholder'] ?? null,
                    'pattern' => $hint['pattern'] ?? null,
                    'id' => $setting->id,
                    'key' => $key,
                    'value' => $setting->value,
                    'type' => $meta['type'],
                    'screen' => $meta['screen'],
                    'order' => $meta['order'],
                    'isPublic' => $setting->is_public,
                ];
            })
            ->where('screen', $screen)
            ->sortBy([['order', 'asc'], ['key', 'asc']])
            ->values()
            ->all();

        return Inertia::render('Admin/Settings/Screen', [
            'screen' => $screen,
            'fields' => $fields,
            // The robots screen shows the file exactly as a crawler receives
            // it, so the editor never has to guess what their edit produced.
            'robotsPreview' => $screen === 'robots' ? app(RobotsBuilder::class)->build() : null,
            'sitemapUrl' => $screen === 'robots' ? url('sitemap.xml') : null,
            'isProduction' => app()->environment('production'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.id' => ['required', 'integer', 'exists:settings,id'],
            // Deliberately untyped: a setting's value may be a string, a
            // boolean, or a list (social channels). The shape is owned by
            // whatever consumes it, not by this endpoint.
            'settings.*.value' => ['nullable'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['settings'] as $row) {
                Setting::query()->whereKey($row['id'])->first()
                    ?->forceFill(['value' => $row['value']])
                    ->save();
            }
        });

        app(Settings::class)->forget();

        return back()->with('success', __('settings.saved'));
    }
}
