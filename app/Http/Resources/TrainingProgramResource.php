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
            'durationWeeks' => $this->duration_weeks,
            'image' => MediaResource::make($this->getFirstMedia('hero')),
        ];
    }
}
