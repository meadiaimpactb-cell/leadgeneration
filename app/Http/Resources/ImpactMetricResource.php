<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ImpactMetric;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImpactMetric
 */
class ImpactMetricResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $value = (float) $this->value_numeric;

        return [
            'id' => $this->id,
            'key' => $this->key,
            // Sent as a number so the count-up animation can drive it, and
            // so the digits render with tabular-nums rather than as a string.
            'value' => $value === floor($value) ? (int) $value : $value,
            'suffix' => $this->value_suffix,
            'year' => $this->year,
            'label' => $this->t('label'),
            'note' => $this->t('note'),
        ];
    }
}
