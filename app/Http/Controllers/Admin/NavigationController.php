<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Support\NavigationBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Header and footer menus (§9.1).
 *
 * Each menu is saved as a whole list rather than item-by-item: reordering,
 * renaming and removing entries is one editing gesture for the client, and
 * saving it as one transaction means a half-applied menu can never render.
 */
class NavigationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('navigation.manage'), 403);

        return Inertia::render('Admin/Navigation', [
            'navigations' => Navigation::query()
                ->with(['items.translations'])
                ->get()
                ->map(fn (Navigation $nav): array => [
                    'id' => $nav->id,
                    'key' => $nav->key,
                    'items' => $nav->items->map(fn (NavigationItem $item): array => [
                        'id' => $item->id,
                        'url' => $item->url,
                        'sortOrder' => $item->sort_order,
                        'isActive' => $item->is_active,
                        'labels' => collect(array_keys(config('site.locales')))
                            ->mapWithKeys(fn (string $l): array => [
                                $l => $item->t('label', $l),
                            ]),
                    ])->values(),
                ]),
            'locales' => array_keys(config('site.locales')),
        ]);
    }

    public function update(Request $request, Navigation $navigation): RedirectResponse
    {
        abort_unless($request->user()->can('navigation.manage'), 403);

        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.url' => ['required', 'string', 'max:512'],
            'items.*.isActive' => ['boolean'],
            'items.*.labels' => ['required', 'array'],
            'items.*.labels.*' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($navigation, $data): void {
            $kept = [];

            foreach ($data['items'] as $position => $row) {
                $item = $navigation->items()->getModel()->query()
                    ->where('navigation_id', $navigation->id)
                    ->whereKey($row['id'] ?? null)
                    ->first()
                    ?? new NavigationItem(['navigation_id' => $navigation->id]);

                $item->fill([
                    'navigation_id' => $navigation->id,
                    'url' => $row['url'],
                    'sort_order' => $position,
                    'is_active' => (bool) ($row['isActive'] ?? true),
                ])->save();

                foreach ($row['labels'] as $locale => $label) {
                    if (blank($label)) {
                        // No label in this locale means the entry is omitted
                        // from that menu rather than shown blank (§12).
                        $item->translations()->where('locale', $locale)->delete();

                        continue;
                    }

                    $item->translations()->updateOrCreate(['locale' => $locale], ['label' => $label]);
                }

                $kept[] = $item->id;
            }

            // Anything the client removed from the list is gone.
            $navigation->items()->getModel()->query()
                ->where('navigation_id', $navigation->id)
                ->whereNotIn('id', $kept)
                ->delete();
        });

        NavigationBuilder::flush();

        return back()->with('success', __('admin.saved'));
    }
}
