<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for Report.
 *
 * @property string $locale
 * @property string|null $title
 * @property string|null $summary
 */
class ReportTranslation extends Model
{
    protected $guarded = ['id'];
}
