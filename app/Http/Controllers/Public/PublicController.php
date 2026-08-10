<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Page;
use App\Services\Seo\MetaBuilder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shared plumbing for the public site.
 *
 * Every public page is a managed `Page` row plus its ordered sections, so the
 * client can restructure any of them from the admin panel without a developer
 * (§9.1). Controllers here supply the entity data a page needs; they never
 * supply copy (§22.1).
 */
abstract class PublicController extends Controller
{
    /** The managed page this request is rendering, if any. */
    protected ?Page $currentPage = null;

    /**
     * Load a managed page and its active sections for the current locale.
     */
    protected function page(string $slug): ?Page
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->with([
                'translations',
                'sections' => fn ($q) => $q->where('is_active', true)
                    ->with(['translations', 'media'])
                    ->orderBy('sort_order'),
            ])
            ->first();

        if ($page === null) {
            return null;
        }

        $resolved = $page->isPublished() || $this->isPreviewing($page) ? $page : null;

        return $this->currentPage = $resolved;
    }

    /**
     * A managed page that must exist for the route to render at all.
     *
     * The listing routes — products, impact, training, partners, solutions,
     * contact — used to render whether or not their page row was published,
     * so unpublishing one from the panel changed nothing on the site. Publish
     * and unpublish have to mean the same thing on every page (§9.1).
     *
     * @return array{0: Page, 1: bool} the page, and whether this is a preview
     */
    protected function requirePage(string $slug): array
    {
        $page = $this->page($slug);

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        return [$page, $this->isPreviewing($page)];
    }

    /**
     * Whether this request carries the page's own secret preview token (§9.1).
     *
     * §9.1 requires drafts to be previewable through a secret link. Without
     * this check the whole feature was decorative: the panel showed a preview
     * button, and following it returned a 404 like any other visitor would get.
     *
     * hash_equals so a wrong token cannot be found by timing the response.
     */
    protected function isPreviewing(Page|Campaign $model): bool
    {
        $supplied = request()->query('preview');

        return is_string($supplied)
            && is_string($model->preview_token)
            && hash_equals($model->preview_token, $supplied);
    }

    /**
     * Flatten a model's sections into the shape the Vue section renderer
     * expects.
     *
     * @return list<array<string, mixed>>
     */
    protected function sections(?Model $model): array
    {
        if ($model === null || ! method_exists($model, 'sections')) {
            return [];
        }

        return $model->sections
            ->map(fn ($section): array => [
                'id' => $section->id,
                'type' => $section->type,
                // Uploaded media wins over a path in `settings`, so an image
                // changed in the panel takes effect without touching JSON.
                'settings' => array_merge(
                    $section->settings ?? [],
                    array_filter([
                        'image' => $section->imagePayload(),
                        'images' => $section->galleryPayload() ?: null,
                    ])
                ),
                'heading' => $section->t('heading'),
                'subheading' => $section->t('subheading'),
                'body' => $section->t('body'),
                'ctaLabel' => $section->t('cta_label'),
                'ctaUrl' => $section->t('cta_url'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string|null>  $overrides
     * @param  list<string>|null  $availableLocales
     * @return array<string, mixed>
     */
    protected function seo(array $overrides = [], ?array $availableLocales = null): array
    {
        // Keywords default from the page being rendered rather than being
        // passed by each controller. Ten call sites that must all remember an
        // optional key is nine chances to forget one, and a page silently
        // missing its target terms is invisible until someone audits the HTML.
        $overrides['keywords'] ??= $this->currentPage?->t('keywords');

        return app(MetaBuilder::class)->build($overrides, $availableLocales);
    }

    /**
     * Breadcrumb trail. Rendered as real links and as BreadcrumbList
     * structured data (§13).
     *
     * @param  list<array{label: string|null, url: string|null}>  $trail
     * @return list<array{label: string|null, url: string|null}>
     */
    protected function breadcrumbs(array $trail): array
    {
        return array_values(array_filter($trail, fn (array $c): bool => filled($c['label'])));
    }
}
