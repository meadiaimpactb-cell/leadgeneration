<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sector
 */
class SectorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'name' => $this->t('name'),
            'summary' => $this->t('summary'),
            // The four audience segments live under /solutions — see the
            // constrained route in routes/web.php.
            'url' => url(app()->getLocale().'/solutions/'.$this->slug),
            'image' => MediaResource::make($this->getFirstMedia('hero')),
        ];
    }
}
