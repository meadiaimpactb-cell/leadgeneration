<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-locale content for TrainingProgram.
 *
 * @property string $locale
 * @property string|null $name
 * @property string|null $summary
 * @property string|null $body
 * @property string|null $outcomes
 * @property string|null $next_cohort
 */
class TrainingProgramTranslation extends Model
{
    protected $guarded = ['id'];
}
