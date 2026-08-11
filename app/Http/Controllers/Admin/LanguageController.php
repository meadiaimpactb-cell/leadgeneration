<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Page;
use App\Models\Setting;
use App\Support\ContentRegistry;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The languages screen (§12).
 *
 * Two questions, one page: is the English site live, and how much of it is
 * actually written. The client asked for the switch because the launch date
 * for English is an administrative decision still open — so it has to be
 * theirs to flip, and they have to be able to see what flipping it would
 * expose.
 *
 * The switch is the `site.english_enabled` row that already governs
 * `Locales`, the language toggle and the sitemap. This screen does not add a
 * second flag; it gives the existing one a home and a consequence you can
 * read before you touch it.
 */
class LanguageController extends Controller
{
    /**
     * The language the switch governs.
     *
     * Named here rather than derived from config: the site ships Arabic plus
     * one, and a screen that silently became a three-language screen because
     * a config key grew would be worse than one that has to be edited on the
     * day a third language is real.
     */
    private const SECONDARY = 'en';

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        return Inertia::render('Admin/Languages', [
            'englishEnabled' => app(Settings::class)->bool('site.english_enabled', true),
            'secondary' => self::SECONDARY,
            'coverage' => $this->coverage(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $data = $request->validate(['englishEnabled' => ['required', 'boolean']]);

        Setting::query()->updateOrCreate(
            ['group' => 'site', 'key' => 'english_enabled'],
            ['value' => $data['englishEnabled']],
        );

        // Both caches read this: the settings store and the locale resolver.
        app(Settings::class)->forget();

        return back()->with('success', __('admin.saved'));
    }

    /**
     * How much of each content type exists in the secondary language.
     *
     * Counted from `translations`, which is where the answer genuinely lives —
     * not from a stored percentage that would start drifting the moment
     * somebody edits a record. Cheap enough to compute per request: one
     * count and one exists-count per entity.
     *
     * @return list<array<string, mixed>>
     */
    private function coverage(): array
    {
        $locale = self::SECONDARY;

        $types = [
            ['key' => 'pages', 'model' => Page::class],
            ['key' => 'campaigns', 'model' => Campaign::class],
        ];

        foreach (ContentRegistry::all() as $entity => $config) {
            $types[] = ['key' => $entity, 'model' => $config['model']];
        }

        return collect($types)
            ->map(function (array $type) use ($locale): array {
                /** @var class-string<Model> $model */
                $model = $type['model'];

                $total = $model::query()->count();
                $translated = $model::query()
                    ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
                    ->count();

                return [
                    'key' => $type['key'],
                    'total' => $total,
                    'translated' => $translated,
                    // An empty content type is not 0% — there is nothing to
                    // translate, and showing it in red would send someone
                    // looking for work that does not exist.
                    'percent' => $total === 0 ? null : (int) round($translated / $total * 100),
                ];
            })
            ->sortBy(fn (array $row): int => $row['percent'] ?? 101)
            ->values()
            ->all();
    }
}
