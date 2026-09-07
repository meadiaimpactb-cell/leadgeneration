<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Page;
use App\Services\Seo\MetaBuilder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The single landing page (management decision, 7 September 2026).
 *
 * The site is one vertically scrolled document served at `/{locale}`. Every
 * string on it is a `section_translations` row, so this controller assembles
 * containers and supplies no copy (§0.1, §22.1).
 *
 * Deliberately thinner than the HomeController it replaces at this URL: that
 * one loaded six datasets — solutions, sectors, metrics, stories, partners —
 * because the multi-page home page rendered rows from six tables. The landing
 * page's content is the approved copy itself, so there is nothing to join.
 * Fewer queries per request is the point, not a side effect: the brief makes
 * performance non-negotiable and this page is the whole site's entry point.
 */
class LandingController extends PublicController
{
    public function __invoke(): Response
    {
        return $this->index();
    }

    public function index(): Response
    {
        $page = Page::query()
            ->where('slug', 'landing')
            ->with([
                'translation',
                'sections' => fn ($q) => $q->where('is_active', true)
                    ->with(['translation', 'media'])
                    ->orderBy('sort_order'),
            ])
            ->first();

        // A draft landing page is visible only through its own secret link (§9.1).
        $previewing = $page !== null && $this->isPreviewing($page);

        if ($page !== null && ! $page->isPublished() && ! $previewing) {
            $page = null;
        }

        return Inertia::render('Public/Landing', [
            'previewing' => $previewing,

            'page' => $page === null ? null : [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
            ],

            'sections' => $page?->sections->map(fn ($section): array => [
                'id' => $section->id,
                'type' => $section->type,
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
            ])->values() ?? [],

            'seo' => app(MetaBuilder::class)->build([
                /*
                 * The search-result title, falling back to the page title.
                 *
                 * `meta_title` was never read here, so the field the panel
                 * offers — and whose own preview shows what Google will print
                 * — changed nothing on the page. Every other public
                 * controller already does this; this one did not.
                 */
                'title' => $page?->t('meta_title') ?: $page?->t('title'),
                'description' => $page?->t('meta_description'),
                'keywords' => $page?->t('keywords'),
                'robots' => $previewing ? 'noindex, nofollow' : null,
            ]),
        ]);
    }
}
