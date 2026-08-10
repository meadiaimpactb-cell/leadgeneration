<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Section;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Attaches the section builder to a model (§8.1).
 *
 * Anything using this trait — pages, solutions, sectors, campaigns — can be
 * composed from ordered, typed sections in the admin panel.
 */
trait HasSections
{
    public function sections(): MorphMany
    {
        return $this->morphMany(Section::class, 'sectionable')
            ->orderBy('sort_order');
    }

    /** Active sections with the active locale's copy already loaded. */
    public function activeSections(?string $locale = null): MorphMany
    {
        return $this->sections()
            ->where('is_active', true)
            ->withTranslation($locale);
    }
}
