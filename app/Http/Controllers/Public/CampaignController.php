<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A campaign landing page (§11.3).
 *
 * No navigation, one goal, no outbound links except legal. Uses
 * CampaignLayout rather than PublicLayout so nothing competes with the form.
 */
class CampaignController extends PublicController
{
    public function show(Request $request, string $locale, string $slug): Response
    {
        $campaign = Campaign::query()
            ->where('slug', $slug)
            ->with([
                'translations',
                'sections' => fn ($q) => $q->where('is_active', true)
                    ->with(['translations', 'media'])
                    ->orderBy('sort_order'),
            ])
            ->first();

        if ($campaign === null || ! $campaign->hasTranslation($locale)) {
            throw new NotFoundHttpException;
        }

        // An unpublished or expired campaign is still reachable by its secret
        // preview token, so the campaigns team can check a page before it
        // goes live (§9.1).
        $previewing = $request->query('preview') === $campaign->preview_token;

        if (! $campaign->isLive() && ! $previewing) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Public/Campaign', [
            'campaign' => [
                'slug' => $campaign->slug,
                'title' => $campaign->t('title'),
                'template' => $campaign->template,
                'settings' => $campaign->settings ?? [],
            ],
            'sections' => $this->sections($campaign),
            'previewing' => $previewing,

            'seo' => $this->seo([
                'title' => $campaign->t('meta_title') ?: $campaign->t('title'),
                'description' => $campaign->t('meta_description'),
                // A live campaign page should rank for its own terms; a
                // preview must never be indexed.
                'robots' => $previewing ? 'noindex, nofollow' : null,
            ], $campaign->translatedLocales()),
        ]);
    }
}
