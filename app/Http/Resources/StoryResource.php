<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Story
 */
class StoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->t('title'),
            'quote' => $this->t('quote'),
            'attribution' => $this->t('attribution'),
            'image' => MediaResource::make($this->getFirstMedia('person')),
        ];
    }
}
