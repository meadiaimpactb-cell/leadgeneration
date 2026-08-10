<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for Page.
 *
 * @property string $locale
 * @property string|null $title
 * @property string|null $subtitle
 * @property string|null $excerpt
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $canonical_override
 */
class PageTranslation extends Model
{
    protected $guarded = ['id'];
}
