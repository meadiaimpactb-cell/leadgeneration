<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for Campaign.
 *
 * @property string $locale
 * @property string|null $title
 * @property string|null $meta_title
 * @property string|null $meta_description
 */
class CampaignTranslation extends Model
{
    protected $guarded = ['id'];
}
