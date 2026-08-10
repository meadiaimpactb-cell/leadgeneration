<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Partners and accreditations (§5).
 */
class PartnerController extends PublicController
{
    public function index(string $locale): Response
    {
        [$page, $previewing] = $this->requirePage('partners');

        $partners = Partner::query()->visible()->translatedIn($locale)
            ->withTranslation()->with('media')->get();

        return Inertia::render('Public/Partners', [
            'previewing' => $previewing,
            'page' => [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
            ],
            'sections' => $this->sections($page),

            // Grouped so the page can head each block separately without the
            // controller deciding what those headings say.
            'partners' => PartnerResource::collection(
                $partners->where('type', Partner::TYPE_PARTNER)->values()
            ),
            'accreditations' => PartnerResource::collection(
                $partners->where('type', Partner::TYPE_ACCREDITATION)->values()
            ),
            'clients' => PartnerResource::collection(
                $partners->where('type', Partner::TYPE_CLIENT)->values()
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
