<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for LeadField.
 *
 * @property string $locale
 * @property string|null $label
 * @property string|null $placeholder
 * @property string|null $help
 */
class LeadFieldTranslation extends Model
{
    protected $guarded = ['id'];
}
