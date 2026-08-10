<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for ImpactMetric.
 *
 * @property string $locale
 * @property string|null $label
 * @property string|null $note
 */
class ImpactMetricTranslation extends Model
{
    protected $guarded = ['id'];
}
