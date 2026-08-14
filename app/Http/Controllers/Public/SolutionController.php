<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Resources\SolutionResource;
use App\Models\Solution;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Solutions for companies (§5): the index and a single solution page.
 */
class SolutionController extends PublicController
{
    public function index(string $locale): Response
    {
        [$page, $previewing] = $this->requirePage('solutions');

        return Inertia::render('Public/Solutions', [
            'previewing' => $previewing,
            'page' => [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
            ],
            'sections' => $this->sections($page),

            'solutions' => SolutionResource::collection(
                Solution::query()->visible()->translatedIn($locale)
                    ->withTranslation()->with('media')->get()
            ),

            'breadcrumbs' => $this->breadcrumbs([
                ['label' => __('common.home'), 'url' => url($locale)],
                ['label' => $page->t('title'), 'url' => null],
            ]),

            'seo' => $this->seo([
                'title' => $page->t('meta_title') ?: $page->t('title'),
                'description' => $page->t('meta_description'),
                // A draft reachable by URL must never reach search results.
                'robots' => $previewing ? 'noindex, nofollow' : null,
            ]),
        ]);
    }

    public function show(string $locale, string $slug): Response
    {
        $solution = Solution::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'translations',
                'media',
                'sections' => fn ($q) => $q->where('is_active', true)
                    ->with(['translations', 'media'])
                    ->orderBy('sort_order'),
            ])
            ->first();

        if ($solution === null || ! $solution->hasTranslation($locale)) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Public/Solution', [
            'solution' => SolutionResource::make($solution),
            'sections' => $this->sections($solution),

            'related' => SolutionResource::collection(
                Solution::query()->visible()->translatedIn($locale)
                    ->whereKeyNot($solution->id)
                    ->withTranslation()->with('media')->limit(3)->get()
            ),

            'breadcrumbs' => $this->breadcrumbs([
                ['label' => __('common.home'), 'url' => url($locale)],
                ['label' => __('nav.solutions'), 'url' => url("{$locale}/solutions")],
                ['label' => $solution->t('name'), 'url' => null],
            ]),

            'seo' => $this->seo([
                'title' => $solution->t('meta_title') ?: $solution->t('name'),
                'description' => $solution->t('meta_description') ?: $solution->t('summary'),
                'image' => $solution->getFirstMediaUrl('hero') ?: null,
                // This page's accordion is its FAQ; `owner` is what
                // SchemaBuilder reads it from (§13). The breadcrumb trail
                // comes from `breadcrumbs()` above without being repeated.
                'owner' => $solution,
            ], $solution->translatedLocales()),
        ]);
    }
}
