<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ShowcaseProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A showcase product. Note what is absent and must stay absent: price,
 * stock, currency, any purchase affordance (§2.2, §4).
 *
 * @mixin ShowcaseProduct
 */
class ShowcaseProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->t('name'),
            'description' => $this->t('description'),
            'craftTechnique' => $this->t('craft_technique'),
            'categoryId' => $this->product_category_id,
            'categoryName' => $this->whenLoaded('category', fn () => $this->category?->t('name')),
            // Informational link only. The frontend renders it as a plain
            // outbound link, never as a "buy" button.
            'storeUrl' => $this->external_store_url,
            'image' => MediaResource::make($this->mediaFor('primary')),
        ];
    }
}
