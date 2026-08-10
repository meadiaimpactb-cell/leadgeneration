<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for Cta.
 *
 * @property string $locale
 * @property string|null $label
 */
class CtaTranslation extends Model
{
    protected $guarded = ['id'];
}
