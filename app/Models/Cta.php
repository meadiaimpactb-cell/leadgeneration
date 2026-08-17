<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable call to action (§8.4).
 *
 * Every CTA on the site ultimately leads to the single contact field (§1) —
 * target_type 'lead' opens forms/LeadModal in place. The other target types
 * exist for legal/footer links, not for a second conversion path.
 */
class Cta extends Model
{
    use HasTranslations;
    use RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
