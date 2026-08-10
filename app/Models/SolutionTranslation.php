<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for Solution.
 *
 * @property string $locale
 * @property string|null $name
 * @property string|null $summary
 * @property string|null $body
 * @property string|null $meta_title
 * @property string|null $meta_description
 */
class SolutionTranslation extends Model
{
    protected $guarded = ['id'];
}
