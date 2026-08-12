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
        $body = $this->t('body');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->t('title'),
            'quote' => $this->t('quote'),
            'body' => $body,
            'attribution' => $this->t('attribution'),
            'image' => MediaResource::make($this->mediaFor('person')),
            'url' => url($request->route('locale').'/impact/stories/'.$this->slug),
            /*
             * The "read the full story" button keys off there being a full
             * story, not off a URL field someone remembers to fill. One fact,
             * one place — the same rule that took the year out of the report
             * titles on this page.
             */
            'hasFullStory' => filled($body),
        ];
    }
}
