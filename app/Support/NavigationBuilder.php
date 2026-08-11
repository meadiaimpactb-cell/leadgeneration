<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Navigation;
use App\Models\NavigationItem;
use Illuminate\Support\Facades\Cache;

/**
 * Turns the `navigations` tables into the menu arrays the header and footer
 * render (§8.4, §9.1).
 *
 * Menus change rarely and are read on every request, so they are cached per
 * locale. The cache is keyed by locale because item labels and resolved URLs
 * both differ between the two versions.
 */
class NavigationBuilder
{
    private const CACHE_PREFIX = 'navigation';

    /**
     * All menus, keyed by their key: header, footer_main, footer_legal.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function all(string $locale): array
    {
        return Cache::remember(
            self::CACHE_PREFIX.".{$locale}",
            now()->addHour(),
            function () use ($locale): array {
                $menus = [];

                foreach (Navigation::query()->where('is_active', true)->get() as $navigation) {
                    $menus[$navigation->key] = $this->items($navigation, $locale);
                }

                return $menus;
            }
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(Navigation $navigation, string $locale): array
    {
        $items = NavigationItem::query()
            ->where('navigation_id', $navigation->id)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['translations', 'children.translations', 'linkable'])
            ->get();

        return $items
            ->map(fn (NavigationItem $item): ?array => $this->item($item, $locale))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function item(NavigationItem $item, string $locale): ?array
    {
        $label = $item->t('label', $locale);
        $url = $item->resolvedUrl($locale);

        // An item with no label in this locale, or pointing at a page that
        // does not exist in it, is omitted rather than shown broken (§12).
        if ($label === null || $url === null) {
            return null;
        }

        /*
         * Two levels, and the guard is the eager-load rather than a counter.
         *
         * `with('children.translations')` loads children for the top level
         * only, so a child's own `children` relation is never loaded — and
         * recursing into it blindly asked the database for it once per child,
         * which `preventLazyLoading` correctly refused. Reading the flag
         * instead stops the recursion exactly where the query stopped, so the
         * menu can never issue a query per item.
         */
        $children = $item->relationLoaded('children')
            ? $item->children
                ->map(fn (NavigationItem $child): ?array => $this->item($child, $locale))
                ->filter()
                ->values()
                ->all()
            : [];

        return [
            'id' => $item->id,
            'label' => $label,
            'url' => $url,
            'children' => $children,
        ];
    }

    public static function flush(): void
    {
        foreach (array_keys(config('site.locales')) as $locale) {
            Cache::forget(self::CACHE_PREFIX.".{$locale}");
        }
    }
}
