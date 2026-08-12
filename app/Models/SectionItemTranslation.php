<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for SectionItem.
 *
 * @property string $locale
 * @property string|null $title
 * @property string|null $body
 * @property string|null $cta_label
 * @property string|null $cta_url
 */
class SectionItemTranslation extends Model
{
    protected $guarded = ['id'];
}
