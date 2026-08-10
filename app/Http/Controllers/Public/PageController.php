<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Pages that are pure section compositions: About, and the legal pages.
 */
class PageController extends PublicController
{
    public function about(string $locale): Response
    {
        return $this->render('about', $locale);
    }

    public function legal(string $locale, string $slug): Response
    {
        return $this->render("legal/{$slug}", $locale, isLegal: true);
    }

    private function render(string $slug, string $locale, bool $isLegal = false): Response
    {
        $page = $this->page($slug);

        if ($page === null || ! $page->hasTranslation($locale)) {
            throw new NotFoundHttpException;
        }

        $previewing = $this->isPreviewing($page);

        return Inertia::render('Public/Page', [
            'previewing' => $previewing,
            'page' => [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
                'template' => $page->template,
                'narrow' => $isLegal,
            ],
            'sections' => $this->sections($page),

            'breadcrumbs' => $this->breadcrumbs([
                ['label' => __('common.home'), 'url' => url($locale)],
                ['label' => $page->t('title'), 'url' => null],
            ]),

            'seo' => $this->seo([
                'title' => $page->t('meta_title') ?: $page->t('title'),
                'description' => $page->t('meta_description'),
                // Legal pages carry no ranking value and dilute the crawl
                // budget across two locales (§13).
                'robots' => $previewing ? 'noindex, nofollow' : ($isLegal ? 'noindex, follow' : null),
            ], $page->translatedLocales()),
        ]);
    }
}
