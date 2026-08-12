<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TrainingProgram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrainingProgram
 */
class TrainingProgramResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->t('name'),
            'summary' => $this->t('summary'),
            'body' => $this->t('body'),
            'outcomes' => $this->t('outcomes'),
            // Optional and usually absent — the card's line appears only while
            // this holds something (§22.1: no invented dates on a page).
            'nextCohort' => $this->t('next_cohort'),
            'durationWeeks' => $this->duration_weeks,
            'image' => MediaResource::make($this->mediaFor('hero')),
        ];
    }
}
