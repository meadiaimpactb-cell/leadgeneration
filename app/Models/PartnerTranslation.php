<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for Partner.
 *
 * @property string $locale
 * @property string|null $display_name
 * @property string|null $note
 */
class PartnerTranslation extends Model
{
    protected $guarded = ['id'];
}
