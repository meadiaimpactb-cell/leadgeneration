<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for ShowcaseProduct.
 *
 * @property string $locale
 * @property string|null $name
 * @property string|null $description
 * @property string|null $craft_technique
 * @property string|null $meta_title
 * @property string|null $meta_description
 */
class ShowcaseProductTranslation extends Model
{
    protected $guarded = ['id'];
}
