<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Solution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Solution
 */
class SolutionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'name' => $this->t('name'),
            'summary' => $this->t('summary'),
            'url' => url(app()->getLocale().'/solutions/'.$this->slug),
            'image' => MediaResource::make($this->getFirstMedia('hero')),
        ];
    }
}
