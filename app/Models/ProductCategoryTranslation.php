<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for ProductCategory.
 *
 * @property string $locale
 * @property string|null $name
 */
class ProductCategoryTranslation extends Model
{
    protected $guarded = ['id'];
}
