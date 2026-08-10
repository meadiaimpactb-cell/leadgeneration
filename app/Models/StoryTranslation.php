<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for Story.
 *
 * @property string $locale
 * @property string|null $title
 * @property string|null $body
 * @property string|null $quote
 * @property string|null $attribution
 */
class StoryTranslation extends Model
{
    protected $guarded = ['id'];
}
