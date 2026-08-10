<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Resources\TrainingProgramResource;
use App\Models\TrainingProgram;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Training and empowerment (§5).
 */
class TrainingController extends PublicController
{
    public function index(string $locale): Response
    {
        [$page, $previewing] = $this->requirePage('training');

        return Inertia::render('Public/Training', [
            'previewing' => $previewing,
            'page' => [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
            ],
            'sections' => $this->sections($page),

            'programs' => TrainingProgramResource::collection(
                TrainingProgram::query()->visible()->translatedIn($locale)
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
}
