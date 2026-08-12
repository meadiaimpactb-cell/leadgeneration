<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Partner
 */
class PartnerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->t('display_name') ?? $this->name,
            'note' => $this->t('note'),
            'url' => $this->website_url,
            'logo' => MediaResource::make($this->mediaFor('logo')),
        ];
    }
}
