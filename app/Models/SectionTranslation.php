<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for Section.
 *
 * @property string $locale
 * @property string|null $heading
 * @property string|null $subheading
 * @property string|null $body
 * @property string|null $cta_label
 * @property string|null $cta_url
 */
class SectionTranslation extends Model
{
    protected $guarded = ['id'];
}
