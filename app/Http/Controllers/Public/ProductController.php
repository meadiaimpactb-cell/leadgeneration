<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Resources\ShowcaseProductResource;
use App\Models\Page;
use App\Models\ProductCategory;
use App\Models\ShowcaseProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The product showcase (§5) — informational only.
 *
 * No price, no cart, no buy button (§2.2). A product may carry a link to the
 * Zid store for anyone who wants to go there, with no sales push (§4).
 *
 * Filtering and paging are done here rather than in the browser. The
 * catalogue is a few hundred products: sending all of them so Vue can hide
 * most would put every product name and image URL into one page's payload,
 * and would give a crawler one enormous page instead of a set of category
 * pages it can index separately (§13).
 */
class ProductController extends PublicController
{
    /** Products per page. Four columns on a wide screen, six rows. */
    private const PER_PAGE = 24;

    public function index(Request $request, string $locale): Response
    {
        [$page, $previewing] = $this->requirePage('products');

        $categories = ProductCategory::query()
            ->visible()->translatedIn($locale)->withTranslation()
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->get()
            // A category whose every product is hidden is a filter that leads
            // to an empty page. Don't offer it.
            ->filter(fn (ProductCategory $c): bool => $c->products_count > 0)
            ->values();

        $active = $this->activeCategory($request, $categories);

        $products = ShowcaseProduct::query()
            ->visible()->translatedIn($locale)
            ->when($active !== null, fn ($q) => $q->where('product_category_id', $active->id))
            ->withTranslation()
            ->with(['media', 'category.translations'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Public/Products', [
            'previewing' => $previewing,
            'page' => [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
            ],
            'sections' => $this->sections($page),

            'categories' => $categories
                ->map(fn (ProductCategory $c): array => [
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'name' => $c->t('name'),
                    'count' => $c->products_count,
                    'url' => $this->categoryUrl($locale, $c->slug),
                ])->all(),

            'activeCategory' => $active?->slug,
            // Counted, not summed from the categories: some products sit in no
            // category at all, so adding the chips up under-reports what "view
            // all" actually shows.
            'totalCount' => ShowcaseProduct::query()->visible()->translatedIn($locale)->count(),
            'allUrl' => $this->categoryUrl($locale, null),

            'products' => ShowcaseProductResource::collection($products->items()),

            // Only what the view needs: which page we are on, and where the
            // neighbouring ones are.
            'pagination' => [
                'current' => $products->currentPage(),
                'last' => $products->lastPage(),
                'total' => $products->total(),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl(),
            ],

            'breadcrumbs' => $this->breadcrumbs(array_values(array_filter([
                ['label' => __('common.home'), 'url' => url($locale)],
                ['label' => $page->t('title'), 'url' => $active === null ? null : $this->categoryUrl($locale, null)],
                $active === null ? null : ['label' => $active->t('name'), 'url' => null],
            ]))),

            'seo' => $this->seo([
                'title' => $this->title($page, $active),
                'description' => $page->t('meta_description'),
                // Self-referencing, so a category page is indexed as its own
                // page rather than folded into the unfiltered listing.
                'canonical' => $products->currentPage() > 1
                    ? $products->url($products->currentPage())
                    : $this->categoryUrl($locale, $active?->slug),
                // A draft reachable by URL must never reach search results.
                'robots' => $previewing ? 'noindex, nofollow' : null,
            ]),
        ]);
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     */
    private function activeCategory(Request $request, $categories): ?ProductCategory
    {
        $slug = $request->query('category');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        // 404 rather than silently showing everything: a mistyped or retired
        // category URL that answers 200 with the full catalogue is a
        // soft-404, which §13 counts as an indexing defect.
        return $categories->firstWhere('slug', $slug)
            ?? throw new NotFoundHttpException;
    }

    private function categoryUrl(string $locale, ?string $slug): string
    {
        return url("{$locale}/products").($slug === null ? '' : '?category='.urlencode($slug));
    }

    /**
     * "Products — Gifts", so a category page has a title of its own in search
     * results instead of competing with the listing under the same one.
     */
    private function title(Page $page, ?ProductCategory $active): ?string
    {
        $base = $page->t('meta_title') ?: $page->t('title');

        if ($active === null) {
            return $base;
        }

        $name = $active->t('name');

        return $name === null ? $base : trim("{$base} — {$name}");
    }
}
