<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The contact page (§5) — one field, nothing else.
 *
 * There is no quote-request form and no qualification form; §2.3 cancelled
 * all of them in favour of this single field.
 */
class ContactController extends PublicController
{
    public function index(string $locale): Response
    {
        [$page, $previewing] = $this->requirePage('contact');

        return Inertia::render('Public/Contact', [
            'previewing' => $previewing,
            'page' => [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
            ],
            'sections' => $this->sections($page),

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
}
