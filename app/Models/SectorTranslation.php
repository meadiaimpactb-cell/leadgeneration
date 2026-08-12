<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for Sector.
 *
 * @property string $locale
 * @property string|null $name
 * @property string|null $summary
 * @property string|null $body
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 */
class SectorTranslation extends Model
{
    protected $guarded = ['id'];
}
